<?php

use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Table\Models\Entity;
use WindowsAzure\Table\Models\EdmType;
use WindowsAzure\Table\Models\TableServiceOptions;

class AzureTableSessionHandler implements SessionHandlerInterface
{
  protected $tableProxy;
  protected $containerName;
  protected $partitionName;

  public function __construct($storageAccount, $storageKey, $containerName = 'phpsess', $partitionName = 'sessions')
  {
    $connectionString = "DefaultEndpointsProtocol=https;" .
                        "AccountName=" . $storageAccount .
                        ";AccountKey=" . $storageKey;
    $this->tableProxy = ServicesBuilder::getInstance()->createTableService($connectionString);
    $this->containerName = $containerName;
    $this->partitionName = $partitionName;

    register_shutdown_function('session_write_close');
  }

  public function __destruct()
  {
    session_write_close();
  }

  public function open($savePath, $sessionName)
  {
    try {
      $this->tableProxy->getTable($this->containerName);
    } catch (ServiceException $e) {
      $this->tableProxy->createTable($this->containerName);
    }
    return true;
  }

  public function close()
  {
    return true;
  }

  public function read($id)
  {
    try {
      $result = $this->tableProxy->getEntity($this->containerName, $this->partitionName, $id);
      $entity = $result->getEntity();
      return unserialize(base64_decode($entity->getPropertyValue('data')));
    } catch (ServiceException $e) {
      return '';
    }
  }

  public function write($id, $data)
  {
    $serializedData = base64_encode(serialize($data));

    try {
      $result = $this->tableProxy->getEntity($this->containerName, $this->partitionName, $id);
      $entity = $result->getEntity();
      $entity->setPropertyValue('data', $serializedData);
      $entity->setPropertyValue('createdat', time());
      $this->tableProxy->updateEntity($this->containerName, $entity);
    } catch (ServiceException $e) {
      $entity = new Entity();
      $entity->setPartitionKey($this->partitionName);
      $entity->setRowKey($id);
      $entity->addProperty('data', EdmType::STRING, $serializedData);
      $entity->addProperty('createdat', EdmType::INT32, time());
      $this->tableProxy->insertEntity($this->containerName, $entity);
    }
    return true;
  }

  public function gc($lifetime)
  {
    $filter = "PartitionKey eq '" . $this->partitionName . "' and createdat lt " . intval(time() - $lifetime);
    try {
      $result = $this->tableProxy->queryEntities($this->containerName, $filter);
      $entities = $result->getEntities();
      foreach ($entities as $entity) {
        $this->tableProxy->deleteEntity($this->containerName, $this->partitionName, $entity->getRowKey());
      }
      return true;
    } catch (ServiceException $e) {
      return false;
    }
  }

  public function destroy($id)
  {
    try {
      $this->tableProxy->deleteEntity($this->containerName, $this->partitionName, $id);
      return true;
    } catch (ServiceException $e) {
      return false;
    }
  }
}
