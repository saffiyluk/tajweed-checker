<?php

namespace App\Helpers;

use Kreait\Firebase\Factory;

class Firebase
{
    public static function firestore()
    {
        $factory = (new Factory)
            ->withServiceAccount(base_path(config('firebase.credentials')))
            ->withDefaultStorageBucket(config('firebase.storage_bucket'));

        return $factory->createFirestore()->database();
    }

    public static function storage()
    {
        $factory = (new Factory)
            ->withServiceAccount(base_path(config('firebase.credentials')))
            ->withDefaultStorageBucket(config('firebase.storage_bucket'));

        return $factory->createStorage()->getBucket();
    }
}
