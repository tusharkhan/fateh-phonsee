<?php

namespace Fateh\Phonsee\Http\Controllers;

use Google\Client;
use Google\Service\Drive;
use Google_Client;
use Illuminate\Support\Facades\DB;

class FatehController
{
    private $storagePath = __DIR__ . '/../../../storage';

    public function createBackup()
    {
        $data = $this->authorizeGoogle();
        return redirect($data);
    }

    private function authorizeGoogle()
    {
        $client = new Client();
        $client->setApplicationName('Fateh');
        $client->setScopes(Drive::DRIVE_FILE);
        $client->setAuthConfig($this->storagePath . '/fateh2.json');
        $client->setAccessType('offline');

        return $client->createAuthUrl();
    }

    private function createDatabaseBackup()
    {
        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host = env('DB_HOST');

        $backupFile = $this->storagePath . '/backups/';
        $backupFile = $backupFile. '/' . $database . '_backup.sql';

        if (!file_exists($backupFile)) {
            $fileHandle = fopen($backupFile, 'w');
            if ($fileHandle) {
                fwrite($fileHandle, '');
                fclose($fileHandle);
            }
        }

        $command = "mysqldump --user={$username} --password={$password} --host={$host} {$database} > {$backupFile}";
        $output = null;
        $resultCode = null;
        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            throw new \Exception("Error deleting the database.");
        }

        return $backupFile;
    }

    private function uploadToGoogleDrive($filePath)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(3000);
        $client = new Client();
        $client->setAccessToken(json_decode(file_get_contents($this->storagePath . '/token.json'), true));
        $driveService = new Drive($client);

        $fileMetadata = new \Google\Service\Drive\DriveFile([
            'name' => basename($filePath),
        ]);
        $content = file_get_contents($filePath);

        $driveService->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => 'application/sql',
            'uploadType' => 'multipart',
        ]);
    }

    public function redirect()
    {
        $code = $_GET['code'];

        $client = new Google_Client();
        $client->setApplicationName('Fateh');
        $client->setScopes(Drive::DRIVE_FILE);
        $client->setAuthConfig($this->storagePath . '/fateh2.json');
        $client->setAccessType('offline');
        $token = $client->fetchAccessTokenWithAuthCode($code);

        $tokenPath = $this->storagePath . '/token.json';

        if (file_exists($tokenPath) && isset($token['access_token']) && $token['access_token']) {
            file_put_contents($tokenPath, '');
            file_put_contents($tokenPath, json_encode($token['access_token']));
        }

        $backupFile = $this->createDatabaseBackup();
        $this->uploadToGoogleDrive($backupFile);
        $this->deleteDatabase();
    }

    private function deleteDatabase()
    {
        $database = env('DB_DATABASE');
        DB::statement("DROP DATABASE `$database`");
    }
}
