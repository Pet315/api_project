<?php

namespace App\Controller;

use Predis\Client as RedisClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadController extends AbstractController
{
    #[Route('/upload', name: 'file_upload')]
    public function upload(Request $request, RedisClient $redis): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        $uploadsDirectory = $this->getParameter('uploads_directory');
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $newFilename = uniqid('', true) . '.' . $file->guessExtension();

        try {
            $file->move($uploadsDirectory, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'File upload failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $fileMetadata = [
            'original_filename' => $file->getClientOriginalName(),
            'new_filename' => $newFilename,
            'upload_time' => date('Y-m-d H:i:s'),
        ];

        $redisKey = 'file_metadata:' . $newFilename;
        foreach ($fileMetadata as $key => $value) {
            $redis->hset($redisKey, $key, $value);
        }

        return new JsonResponse(['filename' => $newFilename], Response::HTTP_OK);
    }
}

