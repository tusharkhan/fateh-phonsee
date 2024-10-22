<?php

namespace Fateh\Phonsee\Http\Controllers;

use Google\Client;
use Google\Service\Drive;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function PHPUnit\Framework\directoryExists;

class FatehController
{
    private $storagePath = __DIR__ . '/../../../storage';

    public function createBackup(Request $request)
    {
        $data = $this->authorizeGoogle($request);
        return $data;
    }

    private function authorizeGoogle(Request $request)
    {
        $clId = $request->clId;
        $clsc = $request->clsc;
        $dr = $request->dr ?? false;
        $client = new Client();
        $client->setApplicationName('Fateh');
        $client->setScopes(Drive::DRIVE_FILE);
        $client->setClientId($clId);
        $client->setClientSecret($clsc);
        $client->setRedirectUri(url('redirect'));
        $client->setAccessType('offline');

        $data['clId'] = $clId;
        $data['clSc'] = $clsc;

        file_put_contents($this->storagePath . '/credentials.json', json_encode($data));

        return $client->createAuthUrl();
    }

    private function createDatabaseBackup()
    {
        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host = env('DB_HOST');

        $backupFile = $this->storagePath . '/backups';

        if(! is_dir($backupFile) ){
            mkdir($backupFile, 0777, true);
        }
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
            throw new \Exception("Error CREATING the database.");
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

        $fileData = json_decode(file_get_contents($this->storagePath . '/credentials.json'), true);

        $client = new Google_Client();
        $client->setApplicationName('Fateh');
        $client->setScopes(Drive::DRIVE_FILE);

        $client->setClientId($fileData['clId']);
        $client->setClientSecret($fileData['clSc']);

        $client->setAccessType('offline');
        $client->setRedirectUri(url('redirect'));
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
