<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Database;

class FirebaseService
{
    private Database $database;

    public function __construct()
    {
        // Path to your Firebase service account key
        $serviceAccountPath = storage_path('app/firebase/service-account.json');

        // Create Firebase instance
        $factory = (new Factory)
            ->withServiceAccount($serviceAccountPath)
            ->withDatabaseUri(config('services.firebase.database_url'));

        $this->database = $factory->createDatabase();
    }

    public function pushData(string $path, array $data): array
    {
        try {
            $newReference = $this->database->getReference($path)->push($data);
            return [
                'success' => true,
                'key' => $newReference->getKey(),
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function setData(string $path, array $data): array
    {
        try {
            $this->database->getReference($path)->set($data);
            return ['success' => true];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function updateData(string $path, array $data): array
    {
        try {
            $this->database->getReference($path)->update($data);
            return ['success' => true];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getData(string $path): array
    {
        try {
            $snapshot = $this->database->getReference($path)->getSnapshot();
            return [
                'success' => true,
                'data' => $snapshot->getValue(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
