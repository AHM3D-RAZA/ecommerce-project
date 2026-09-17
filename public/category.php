<?php
require_once __DIR__ . '/../core/Database.php';

$db = new Database();

// ---- Read filters from the query string ----
$slug     = trim($_GET['slug'] ?? '');
$search   = trim($_GET['q'] ?? '');
$minPrice = ($_GET['min_price'] ?? '') !== '' ? (float) $_GET['min_price'] : null;
$maxPrice = ($_GET['max_price'] ?? '') !== '' ? (float) $_GET['max_price'] : null;
$sort     = $_GET['sort'] ?? 'newest';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 9;

$activeCategory = null;
if ($slug !== '') {
    $activeCategory = $db->selectOne("SELECT * FROM categories WHERE slug = ? AND status = 1", [$slug]);
}

// ---- Build the WHERE clause piece by piece ----
$where = ['p.status = 1'];
$params = [];

if ($activeCategory) {
    $where[] = 'p.category_id = ?';
    $params[] = $activeCategory['id'];
}
if ($search !== '') {
    $where[] = 'p.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($minPrice !== null) {
    $where[] = 'p.price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = 'p.price <= ?';
    $params[] = $maxPrice;
}
$whereSql = implode(' AND ', $where);

$orderBy = match ($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc'   => 'p.name ASC',
    default      => 'p.id DESC', // newest
};

// ---- Total count (for pagination + "Showing X of Y") ----
$totalRow = $db->selectOne("SELECT COUNT(*) AS total FROM products p WHERE $whereSql", $params);
$total = (int) $totalRow['total'];
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// ---- The actual product list for this page ----
$products = $db->select(
    "SELECT p.* FROM products p WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset",
    $params
);

// ---- Category list with live product counts, for the sidebar ----
$categories = $db->select(
    "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 1) AS product_count
     FROM categories c WHERE c.status = 1 ORDER BY c.name ASC"
);

$pageTitle = $activeCategory ? $activeCategory['name'] : ($search !== '' ? 'Search results' : 'Shop');
$pageScript = 'demo-4.js';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/product-card.php';

// Helper to rebuild the query string while overriding one or more params
function category_url($overrides = []) {
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return 'category.php?' . http_build_query($params);
}
?>
            <div class="page-header text-center" style="background-image: url('assets/images/page-header-bg.jpg')">
                <div class="container">
                    <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?><span>Shop</span></h1>
                </div>
            </div><!-- End .page-header -->

            <nav aria-label="breadcrumb" class="breadcrumb-nav mb-2">
                <div class="container">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="category.php">Shop</a></li>
                        <?php if ($activeCategory): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($activeCategory['name']) ?></li>
                        <?php elseif ($search !== ''): ?>
                            <li class="breadcrumb-item active" aria-current="page">Search: "<?= htmlspecialchars($search) ?>"</li>
                        <?php endif; ?>
                    </ol>
                </div>
            </nav><!-- End .breadcrumb-nav -->

            <div class="page-content">
                <div class="container">
                    <div class="row">
                        <div class="col-lg-9">
                            <div class="toolbox">
                                <div class="toolbox-left">
                                    <div class="toolbox-info">
                                        <?php if ($total === 0): ?>
                                            No products found
                                        <?php else: ?>
                                            Showing <span><?= $offset + 1 ?>-<?= min($offset + $perPage, $total) ?> of <?= $total ?></span> Products
                                        <?php endif; ?>
                                    </div>
                                </div><!-- End .toolbox-left -->

                                <div class="toolbox-right">
                                    <div class="toolbox-sort">
                                        <label for="sortby">Sort by:</label>
                                        <div class="select-custom">
                                            <select name="sortby" id="sortby" class="form-control" onchange="window.location.href=this.value">
                                                <?php
                                                $sortOptions = [
                                                    'newest'     => 'Newest',
                                                    'price_asc'  => 'Price: Low to High',
                                                    'price_desc' => 'Price: High to Low',
                                                    'name_asc'   => 'Name: A to Z',
                                                ];
                                                foreach ($sortOptions as $value => $label):
                                                ?>
                                                    <option value="<?= htmlspecialchars(category_url(['sort' => $value, 'page' => null])) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= $label ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div><!-- End .toolbox-sort -->
                                </div><!-- End .toolbox-right -->
                            </div><!-- End .toolbox -->

                            <?php if (empty($products)): ?>
                                <div class="text-center py-5">
                                    <p>No products match your filters right now.</p>
                                    <a href="category.php" class="btn btn-outline-dark-2 btn-round"><span>Clear filters</span><i class="icon-long-arrow-right"></i></a>
                                </div>
                            <?php else: ?>
                                <div class="products mb-3">
                                    <div class="row justify-content-center">
                                        <?php foreach ($products as $p): ?>
                                            <div class="col-6 col-md-4">
                                                <?php render_product_card($p); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div><!-- End .products -->

                                <?php if ($totalPages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link page-link-prev" href="<?= category_url(['page' => max(1, $page - 1)]) ?>" aria-label="Previous">
                                                    <i class="icon-long-arrow-left"></i> Prev
                                                </a>
                                            </li>
                                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= category_url(['page' => $i]) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link page-link-next" href="<?= category_url(['page' => min($totalPages, $page + 1)]) ?>" aria-label="Next">
                                                    Next <i class="icon-long-arrow-right"></i>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div><!-- End .col-lg-9 -->

                        <aside class="col-lg-3 order-lg-first">
                            <div class="sidebar sidebar-shop">
                                <div class="widget widget-clean">
                                    <label>Filters:</label>
                                    <a href="category.php" class="sidebar-filter-clear">Clear All</a>
                                </div><!-- End .widget-clean -->

                                <div class="widget widget-collapsible">
                                    <h3 class="widget-title">
                                        <a data-toggle="collapse" href="#widget-cat" role="button" aria-expanded="true" aria-controls="widget-cat">Category</a>
                                    </h3>

                                    <div class="collapse show" id="widget-cat">
                                        <div class="widget-body">
                                            <div class="filter-items filter-items-count">
                                                <div class="filter-item">
                                                    <a href="category.php" class="<?= !$activeCategory ? 'font-weight-bold' : '' ?>">All Categories</a>
                                                </div>
                                                <?php foreach ($categories as $cat): ?>
                                                    <div class="filter-item">
                                                        <a href="<?= category_url(['slug' => $cat['slug'], 'page' => null]) ?>" class="<?= ($activeCategory && $activeCategory['id'] == $cat['id']) ? 'font-weight-bold' : '' ?>">
                                                            <?= htmlspecialchars($cat['name']) ?>
                                                        </a>
                                                        <span class="item-count"><?= (int) $cat['product_count'] ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- End .widget -->

                                <div class="widget widget-collapsible">
                                    <h3 class="widget-title">
                                        <a data-toggle="collapse" href="#widget-price" role="button" aria-expanded="true" aria-controls="widget-price">Price</a>
                                    </h3>

                                    <div class="collapse show" id="widget-price">
                                        <div class="widget-body">
                                            <form action="category.php" method="get" class="filter-price">
                                                <?php if ($slug !== ''): ?><input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>"><?php endif; ?>
                                                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                                                <div class="form-row">
                                                    <div class="col-6">
                                                        <label for="min_price" class="sr-only">Min price</label>
                                                        <input type="number" min="0" step="1" name="min_price" id="min_price" class="form-control form-control-sm" placeholder="Min" value="<?= $minPrice !== null ? (int) $minPrice : '' ?>">
                                                    </div>
                                                    <div class="col-6">
                                                        <label for="max_price" class="sr-only">Max price</label>
                                                        <input type="number" min="0" step="1" name="max_price" id="max_price" class="form-control form-control-sm" placeholder="Max" value="<?= $maxPrice !== null ? (int) $maxPrice : '' ?>">
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm btn-block mt-2">Apply</button>
                                            </form>
                                        </div>
                                    </div>
                                </div><!-- End .widget -->
                            </div><!-- End .sidebar-shop -->
                        </aside><!-- End .col-lg-3 -->
                    </div><!-- End .row -->
                </div><!-- End .container -->
            </div><!-- End .page-content -->
        </main><!-- End .main -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
