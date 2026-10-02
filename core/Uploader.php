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
        $check = self::validate($file);
        if ($check['empty']) {
            return ['success' => false, 'message' => null]; // nothing uploaded, not necessarily an error
        }
        if (!$check['ok']) {
            return ['success' => false, 'message' => $check['message']];
        }

        $extension = $check['ext'];
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

    // Validates one $_FILES entry. An empty slot is not an error - it just
    // comes back with empty = true so callers can skip it.
    public static function validate($file)
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'empty' => true, 'ext' => null, 'message' => null];
        }

        $label = (isset($file['name']) && $file['name'] !== '') ? '"' . $file['name'] . '" ' : '';

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'empty' => false, 'ext' => null,
                'message' => 'The file ' . $label . 'could not be uploaded. Please try again.'];
        }

        if (!isset($file['size']) || $file['size'] > self::MAX_BYTES) {
            return ['ok' => false, 'empty' => false, 'ext' => null,
                'message' => 'The file ' . $label . 'is too large. Maximum size is 2MB.'];
        }

        $mime = @mime_content_type($file['tmp_name']);

        if (!isset(self::ALLOWED_TYPES[$mime])) {
            return ['ok' => false, 'empty' => false, 'ext' => null,
                'message' => 'The file ' . $label . 'is not a JPG, PNG, WEBP or GIF image.'];
        }

        return ['ok' => true, 'empty' => false, 'ext' => self::ALLOWED_TYPES[$mime], 'message' => null];
    }

    // Save an upload into the shared staging folder (uploads/tmp/) so a product
    // can be created first and its images moved into place afterwards. Returns
    // the staged filename (not a path) so it can be moved or deleted later.
    public static function stage($file)
    {
        $check = self::validate($file);
        if ($check['empty']) {
            return ['success' => false, 'empty' => true, 'file' => null, 'message' => null];
        }
        if (!$check['ok']) {
            return ['success' => false, 'empty' => false, 'file' => null, 'message' => $check['message']];
        }

        $dir = self::stagingDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $staged = bin2hex(random_bytes(12)) . '.' . $check['ext'];

        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $staged)) {
            return ['success' => false, 'empty' => false, 'file' => null,
                'message' => 'Could not save the uploaded image.'];
        }

        return ['success' => true, 'empty' => false, 'file' => $staged, 'message' => null];
    }

    // Move a staged file into $targetDir, reusing the original name where
    // possible (made unique so files never overwrite each other). Returns the
    // final filename, or null on failure.
    public static function moveStaged($stagedName, $targetDir, $originalName = null)
    {
        $source = self::stagingDir() . '/' . $stagedName;
        if (!is_file($source)) {
            return null;
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $extension = strtolower(pathinfo($stagedName, PATHINFO_EXTENSION));
        $filename = self::safeName($originalName, $extension);

        if ($filename === '') {
            $filename = bin2hex(random_bytes(10)) . '.' . $extension;
        }

        $filename = self::uniqueName($targetDir, $filename);

        if (!@rename($source, $targetDir . '/' . $filename)) {
            return null;
        }

        return $filename;
    }

    public static function deleteStaged($stagedName)
    {
        $file = self::stagingDir() . '/' . $stagedName;
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function stagingDir()
    {
        return __DIR__ . '/../public/uploads/tmp';
    }

    // Turn a user-supplied filename into something safe to store while keeping
    // the real extension, so the public URL still ends in .jpg/.png/etc.
    public static function safeName($originalName, $extension)
    {
        if (!$originalName) {
            return '';
        }

        $base = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = trim($base, '-');

        if ($base === '') {
            return '';
        }

        return substr($base, 0, 60) . '.' . $extension;
    }

    // Avoid clobbering an existing file in the target folder.
    public static function uniqueName($dir, $filename)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);

        $candidate = $filename;
        $n = 2;
        while (file_exists($dir . '/' . $candidate)) {
            $candidate = $base . '-' . $n . '.' . $extension;
            $n++;
        }

        return $candidate;
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

    // Recursively delete a folder that lives inside public/uploads/ (used to
    // wipe a product's whole image folder when the product is deleted).
    public static function deleteFolder($dir)
    {
        $uploadsRoot = realpath(__DIR__ . '/../public/uploads');
        $real = is_dir($dir) ? realpath($dir) : false;

        // Never step outside public/uploads/ - protects the bundled template images.
        if (!$uploadsRoot || !$real || strpos($real, $uploadsRoot) !== 0) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($real);
    }
}
