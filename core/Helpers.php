<?php

// Seed data (from schema.sql) stores images as short paths like "products/product-1.jpg"
// which live under assets/images/demos/demo-4/. Anything uploaded through the admin
// dashboard is saved under public/uploads/... and is already a full relative path.
// This one function is used everywhere a product or category image is printed so
// both kinds of image work without the pages needing to know the difference.
function shop_image($path)
{
    $path = trim((string) $path);

    if ($path === '') {
        return 'assets/images/demos/demo-4/products/product-1.jpg';
    }

    if (strpos($path, 'uploads/') === 0) {
        return $path;
    }

    return 'assets/images/demos/demo-4/' . $path;
}

// Turns "Smart Watches & Bands" into "smart-watches-bands" for URLs.
function make_slug($text)
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}
