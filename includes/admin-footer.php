        </div>
    </main>

    <script src="<?= $base ?>assets/js/core/bootstrap.bundle.min.js"></script>
    <!-- perfect-scrollbar must load BEFORE material-dashboard.min.js: that script
         instantiates new PerfectScrollbar(...) at the top level, so a missing
         global throws a ReferenceError that kills all admin JS (floating labels,
         navbar, tooltips...). The matching .ps* styles are already in the theme CSS. -->
    <script src="<?= $base ?>assets/js/perfect-scrollbar.min.js"></script>
    <script src="<?= $base ?>assets/js/material-dashboard.min.js"></script>
    <script src="<?= $base ?>assets/js/admin-ui.js"></script>
    <!-- Note: chartjs.min.js is loaded in admin-header.php (when $pageScript === 'chart') so it
         is available before the pages' inline new Chart(...) scripts further down the body. -->
</body>
</html>
