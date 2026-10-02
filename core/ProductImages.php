<?php

require_once __DIR__ . '/Uploader.php';

/**
 * Central place for everything to do with a product's image set.
 *
 * products.image holds a JSON array of tokens in gallery order - the first
 * entry is the hero/thumbnail. A token is one of:
 *
 *   - a bare filename           -> public/uploads/products/{id}/{filename}
 *   - a path starting uploads/  -> a legacy full upload path, used as-is
 *   - any other relative path   -> a bundled template seed image under
 *                                  assets/images/demos/demo-4/
 *
 * Uploaded files live in a per-product folder with NO extra images/ subfolder:
 *   public/uploads/products/{productId}/
 * Public URLs are built from the product id + the rawurlencode()d filename.
 *
 * Nothing outside this class should read the JSON column directly.
 */
class ProductImages
{
    const MAX_IMAGES = 5;

    const PLACEHOLDER = 'assets/images/demos/demo-4/products/product-1.jpg';

    // ---- encode / decode --------------------------------------------------

    // Decode the stored value into a clean, ordered list of tokens.
    public static function decode($value)
    {
        if (is_array($value)) {
            $tokens = $value;
        } else {
            $value = trim((string) $value);
            $decoded = $value !== '' ? json_decode($value, true) : null;
            // Fall back to treating a legacy single path as a one-item list.
            $tokens = is_array($decoded) ? $decoded : ($value !== '' ? [$value] : []);
        }

        $clean = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token !== '') {
                $clean[] = $token;
            }
        }

        return array_values($clean);
    }

    // Encode an ordered token list for storage. The column is a native JSON
    // type, so an invalid string would be rejected by MySQL - fall back to an
    // empty array rather than ever writing something unencodable.
    public static function encode(array $tokens)
    {
        $json = json_encode(self::decode($tokens), JSON_UNESCAPED_SLASHES);

        return $json === false ? '[]' : $json;
    }

    // The hero/thumbnail token ('' when there are no images).
    public static function first($value)
    {
        $tokens = self::decode($value);
        return $tokens[0] ?? '';
    }

    public static function count($value)
    {
        return count(self::decode($value));
    }

    // How many more images a product may still take.
    public static function availableSlots($value)
    {
        return max(0, self::MAX_IMAGES - self::count($value));
    }

    // ---- paths / urls -----------------------------------------------------

    public static function productDir($productId)
    {
        return __DIR__ . '/../public/uploads/products/' . (int) $productId;
    }

    public static function filesystemPath($productId, $token)
    {
        return self::productDir($productId) . '/' . basename($token);
    }

    // Public URL for a token belonging to $productId.
    public static function url($productId, $token)
    {
        $token = trim((string) $token);

        if ($token === '') {
            return '';
        }

        if (strpos($token, 'uploads/') === 0) {
            // Legacy full upload path: uploads/products/xxxx.jpg
            $segments = explode('/', $token);
            $file = array_pop($segments);
            return implode('/', $segments) . '/' . rawurlencode($file);
        }

        if (strpos($token, '/') === false) {
            // Bare filename inside this product's folder.
            return 'uploads/products/' . (int) $productId . '/' . rawurlencode($token);
        }

        // Bundled template image (e.g. products/product-1.jpg, cats/1.png).
        return 'assets/images/demos/demo-4/' . $token;
    }

    // URL for the hero image of a product row (placeholder when it has none).
    public static function heroUrl($product)
    {
        $id = is_array($product) ? (int) ($product['id'] ?? 0) : (int) $product;
        $value = is_array($product) ? ($product['image'] ?? '') : '';
        $token = self::first($value);

        return $token !== '' ? self::url($id, $token) : self::PLACEHOLDER;
    }

    // All image URLs for a product, in gallery order.
    public static function urls($product)
    {
        $id = is_array($product) ? (int) ($product['id'] ?? 0) : (int) $product;
        $value = is_array($product) ? ($product['image'] ?? '') : '';

        $urls = [];
        foreach (self::decode($value) as $token) {
            $urls[] = self::url($id, $token);
        }

        return $urls;
    }

    // Filenames currently on disk in the product folder (uploaded images only).
    public static function availableFiles($productId)
    {
        $dir = self::productDir($productId);
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (scandir($dir) as $entry) {
            if ($entry !== '.' && $entry !== '..' && is_file($dir . '/' . $entry)) {
                $files[] = $entry;
            }
        }
        sort($files);

        return $files;
    }

    // Remove the product's entire image folder.
    public static function deleteProductFolder($productId)
    {
        Uploader::deleteFolder(self::productDir($productId));
    }

    // Move a staged temp file into the product folder; returns the final name.
    public static function moveStagedIntoProduct($stagedName, $productId, $originalName = null)
    {
        return Uploader::moveStaged($stagedName, self::productDir($productId), $originalName);
    }

    // Delete one existing token's file from disk. Only ever touches uploads/,
    // so bundled seed images (e.g. products/product-1.jpg) are never removed.
    public static function deleteTokenFile($productId, $token)
    {
        $token = trim((string) $token);

        if ($token === '') {
            return;
        }

        if (strpos($token, 'uploads/') === 0) {
            Uploader::delete($token);
            return;
        }

        if (strpos($token, '/') === false) {
            $file = self::productDir($productId) . '/' . $token;
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    // ---- multi-file upload handling ---------------------------------------

    /**
     * Stage the files from a "name=images[]" submission.
     *
     * $files is $_FILES['images'] (parallel arrays). Original input indexes are
     * preserved, empty slots are skipped, rejected files are counted and the
     * remaining-capacity cap is enforced. Returns:
     *   staged    => [ inputIndex => stagedFilename ]
     *   originals => [ inputIndex => originalClientFilename ]
     *   accepted / skipped / errors
     */
    public static function stageUploads($files, $maxAllowed)
    {
        $result = ['staged' => [], 'originals' => [], 'accepted' => 0, 'skipped' => 0, 'errors' => []];

        if (!isset($files['name']) || !is_array($files['name'])) {
            return $result;
        }

        $capacityReported = false;

        foreach ($files['name'] as $index => $name) {
            $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue; // empty slot - never an error, just skip it
            }

            if ($result['accepted'] >= $maxAllowed) {
                $result['skipped']++;
                if (!$capacityReported) {
                    if ($maxAllowed <= 0) {
                        $result['errors'][] = 'No images added - the limit of ' . self::MAX_IMAGES . ' is already reached.';
                    } elseif ($maxAllowed >= self::MAX_IMAGES) {
                        $result['errors'][] = 'Only ' . self::MAX_IMAGES . ' images are allowed - the extra files were skipped.';
                    } else {
                        $result['errors'][] = 'You can add ' . $maxAllowed . ' more (limit ' . self::MAX_IMAGES . ').';
                    }
                    $capacityReported = true;
                }
                continue;
            }

            $single = [
                'name'     => $name,
                'type'     => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error'    => $error,
                'size'     => $files['size'][$index] ?? 0,
            ];

            $staged = Uploader::stage($single);
            if ($staged['success']) {
                $result['staged'][$index] = $staged['file'];
                $result['originals'][$index] = $name;
                $result['accepted']++;
            } else {
                $result['skipped']++;
                if (!empty($staged['message'])) {
                    $result['errors'][] = $staged['message'];
                }
            }
        }

        return $result;
    }

    // Delete every staged file in a stageUploads() result (cleanup on failure).
    public static function discardStaged($stage)
    {
        foreach ($stage['staged'] ?? [] as $stagedName) {
            Uploader::deleteStaged($stagedName);
        }
    }
}

