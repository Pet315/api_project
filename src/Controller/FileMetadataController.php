<?php

namespace App\Controller;

use Predis\Client as RedisClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class FileMetadataController extends AbstractController
{
    #[Route('/metadata', name: 'metadata')]
    public function getAllFileMetadata(RedisClient $redis): JsonResponse
    {
        $keys = $redis->keys('file_metadata:*');

        if (empty($keys)) {
            return new JsonResponse(['message' => 'File metadata not found'], Response::HTTP_NOT_FOUND);
        }

        $allMetadata = [];
        foreach ($keys as $key) {
            $fileMetadata = $redis->hgetall($key);
            $allMetadata[$key] = $fileMetadata;
        }

        return new JsonResponse($allMetadata, Response::HTTP_OK);
    }
}
