        </main>
        <footer class="app-footer">
            &copy; <?= date('Y') ?> <?= e(setting('nama_lembaga', APP_NAME)) ?> &middot; <?= APP_DESC ?>
        </footer>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($useChart)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= asset('js/app.js') ?>"></script>
<?= $pageScript ?? '' ?>
</body>
</html>
