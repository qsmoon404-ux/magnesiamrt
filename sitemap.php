<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/xml; charset=utf-8');
$b = base_url();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= htmlspecialchars($b) ?>/</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>
  <url><loc><?= htmlspecialchars($b) ?>/privacy.php</loc><changefreq>yearly</changefreq><priority>0.3</priority></url>
</urlset>
