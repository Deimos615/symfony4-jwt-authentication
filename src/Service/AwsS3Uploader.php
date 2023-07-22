<?php
namespace App\Service;

use Aws\S3\S3Client;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AwsS3Uploader
{
    private $s3Client;
    private $bucketName;

    public function __construct(S3Client $s3Client, string $bucketName)
    {
        $this->s3Client = $s3Client;
        $this->bucketName = $bucketName;
    }

    public function uploadUserPhoto(UploadedFile $file, string $userPhotoKey): bool
    {
        $result = $this->s3Client->putObject([
            'Bucket' => $this->bucketName,
            'Key' => $userPhotoKey, // The unique key for the user's photo in S3
            'SourceFile' => $file->getPathname(),
        ]);

        // Check if the upload was successful
        return $result['@metadata']['statusCode'] === 200;
    }
}