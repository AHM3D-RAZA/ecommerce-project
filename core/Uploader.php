<?php

// Handles a single uploaded image: checks it's really an image, checks the
// size, and saves it under public/uploads/{folder}/ with a random name so
// two people uploading "photo.jpg" never overwrite each other.
class Uploader
{
    const MAX_BYTES = 2 * 1024 * 1024; // 2MB

    const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    // $file is one entry from $_FILES, $folder is 'products' or 'categories'.
    // Returns ['success' => true, 'path' => 'uploads/products/xxxx.jpg']
    // or ['success' => false, 'message' => '...'].
    public static function save($file, $folder)
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'message' => null]; // nothing uploaded, not necessarily an error
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'The file could not be uploaded. Please try again.'];
        }

        if ($file['size'] > self::MAX_BYTES) {
            return ['success' => false, 'message' => 'Image is too large. Maximum size is 2MB.'];
        }

        $mime = mime_content_type($file['tmp_name']);

        if (!isset(self::ALLOWED_TYPES[$mime])) {
            return ['success' => false, 'message' => 'Only JPG, PNG, WEBP or GIF images are allowed.'];
        }

        $extension = self::ALLOWED_TYPES[$mime];
        $filename = bin2hex(random_bytes(10)) . '.' . $extension;

        $targetDir = __DIR__ . '/../public/uploads/' . $folder . '/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
            return ['success' => false, 'message' => 'Could not save the uploaded image.'];
        }

        return ['success' => true, 'path' => 'uploads/' . $folder . '/' . $filename];
    }

    // Deletes a previously uploaded image, but only ever touches files inside
    // public/uploads/ - never the seed images that ship with the template.
    public static function delete($path)
    {
        if ($path && strpos($path, 'uploads/') === 0) {
            $full = __DIR__ . '/../public/' . $path;
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }
}
