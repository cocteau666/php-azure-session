php-azure-session
=================

Save PHP session to Microsoft Azure Table Storage.
Azure SDK for PHP needed.


Usage
=================
Write below.

require 'AzureTableSessionHandler.php';
$sessionHandler = new AzureTableSessionHandler($blob_account, $blob_key);
session_set_save_handler($sessionHandler, TRUE);
session_start();

