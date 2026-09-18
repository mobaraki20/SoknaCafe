<?php
declare(strict_types=1);

require_once __DIR__ . '/relay_client.php';

const SOKNA_GUEST_SNAPSHOT_FORMAT = 'sokna-guest-snapshot-v1';

function guest_publish_public_item(array $item, array $tags = []): array
{
    return [
        'id'=>(int)$item['id'],
        'item_code'=>(string)($item['item_code'] ?? ''),
        'category_id'=>(int)$item['category_id'],
        'category_name'=>(string)($item['category_name'] ?? ''),
        'name'=>(string)$item['name'],
        'description'=>(string)($item['description'] ?? ''),
        'price'=>(int)$item['price'],
        'image_path'=>(string)($item['image_path'] ?? ''),
        'available'=>(int)$item['available'] === 1,
        'featured'=>(int)($item['featured'] ?? 0) === 1,
        'takeaway_allowed'=>(int)($item['takeaway_allowed'] ?? 1) === 1,
        'preparation_station'=>(string)($item['preparation_station'] ?? 'cold_bar'),
        'suggested_item_id'=>$item['suggested_item_id'] !== null ? (int)$item['suggested_item_id'] : null,
        'sort_order'=>(int)($item['item_sort'] ?? 0),
        'tags'=>array_map(static fn(array $tag): array => [
            'title'=>(string)($tag['title'] ?? ''),
            'color_key'=>(string)($tag['color_key'] ?? ''),
            'icon'=>(string)($tag['icon'] ?? ''),
        ], $tags),
    ];
}

function guest_publish_media_candidate(string $path): ?array
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || str_contains($path, '..') || preg_match('#^[a-z]+://#i', $path)) return null;
    $relative = ltrim($path, '/');
    $root = realpath(dirname(__DIR__));
    if ($root === false) return null;
    $absolute = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    if ($absolute === false || !is_file($absolute)) return null;
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($absolute, $prefix)) return null;
    $size = filesize($absolute);
    if ($size === false || $size < 1 || $size > 8 * 1024 * 1024) return null;
    $mime = function_exists('mime_content_type') ? (string)@mime_content_type($absolute) : '';
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','image/svg+xml'=>'svg'];
    if (!isset($allowed[$mime])) return null;
    $hash = hash_file('sha256', $absolute);
    if (!is_string($hash) || strlen($hash) !== 64) return null;
    $width=1;$height=1;
    $dimensions=@getimagesize($absolute);
    if(is_array($dimensions)){
        $width=max(1,(int)($dimensions[0]??1));
        $height=max(1,(int)($dimensions[1]??1));
    }elseif($mime==='image/svg+xml'){
        $svg=(string)@file_get_contents($absolute);
        if(preg_match('/<svg[^>]*\bwidth=["\']?([0-9.]+)/i',$svg,$m))$width=max(1,(int)round((float)$m[1]));
        if(preg_match('/<svg[^>]*\bheight=["\']?([0-9.]+)/i',$svg,$m))$height=max(1,(int)round((float)$m[1]));
        if(($width===1||$height===1)&&preg_match('/<svg[^>]*\bviewBox=["\'][^"\']*?([0-9.]+)\s+([0-9.]+)["\']/i',$svg,$m)){
            $width=max($width,(int)round((float)$m[1]));$height=max($height,(int)round((float)$m[2]));
        }
    }
    return [
        'source_path'=>$relative,'sha256'=>$hash,'mime'=>$mime,'extension'=>$allowed[$mime],
        'size'=>(int)$size,'width'=>$width,'height'=>$height,'absolute_path'=>$absolute,
    ];
}

function guest_publish_collect_media(array $paths): array
{
    $manifest = [];
    foreach (array_values(array_unique(array_filter(array_map('strval', $paths)))) as $path) {
        $media = guest_publish_media_candidate($path);
        if (!$media) continue;
        $manifest[$media['source_path']] = [
            'sha256'=>$media['sha256'],'mime'=>$media['mime'],'extension'=>$media['extension'],'size'=>$media['size'],
            'width'=>$media['width'],'height'=>$media['height'],
        ];
    }
    ksort($manifest, SORT_STRING);
    return $manifest;
}

function guest_publish_build_snapshot(PDO $pdo): array
{
    $menus = menu_catalog_visible_menus($pdo);
    $catalogs = [];
    $mediaPaths = [];
    foreach ($menus as $menu) {
        $catalog = menu_catalog_snapshot($pdo, 'guest_public', (string)$menu['menu_key'], false);
        $ids = array_map('intval', array_column($catalog['items'], 'id'));
        $tagsByItem = $ids ? item_tags_for($ids) : [];
        $items = [];
        foreach ($catalog['items'] as $item) {
            $items[] = guest_publish_public_item($item, $tagsByItem[(int)$item['id']] ?? []);
            if (!empty($item['image_path'])) $mediaPaths[] = (string)$item['image_path'];
        }
        $categories = array_map(static function (array $category) use (&$mediaPaths): array {
            if (!empty($category['image_path'])) $mediaPaths[] = (string)$category['image_path'];
            return [
                'id'=>(int)$category['id'],'category_key'=>(string)$category['category_key'],
                'name'=>(string)$category['name'],'image_path'=>(string)($category['image_path'] ?? ''),
                'icon_key'=>(string)($category['icon_key'] ?? ''),'sort_order'=>(int)$category['sort_order'],
            ];
        }, $catalog['categories']);
        $catalogs[(string)$menu['menu_key']] = [
            'menu'=>['menu_key'=>(string)$menu['menu_key'],'name'=>(string)$menu['name'],'sort_order'=>(int)$menu['sort_order']],
            'categories'=>$categories,'items'=>$items,
        ];
    }

    $tables = $pdo->query("SELECT id,name,code,access_token,table_number,sort_order,zone_label
        FROM cafe_tables WHERE active=1 ORDER BY table_number,sort_order,id")->fetchAll(PDO::FETCH_ASSOC);
    $tables = array_map(static fn(array $row): array => [
        'id'=>(int)$row['id'],'name'=>(string)$row['name'],'code'=>(string)$row['code'],'token'=>(string)$row['access_token'],
        'public_ref'=>substr(hash('sha256',(string)$row['access_token']),0,32),
        'table_number'=>(int)$row['table_number'],'sort_order'=>(int)($row['sort_order'] ?? 0),
        'zone_label'=>(string)($row['zone_label'] ?? ''),
    ], $tables);

    $logoPath = trim(setting('logo_path'));
    if ($logoPath !== '') $mediaPaths[] = $logoPath;

    $marketing = ['campaign'=>null,'events'=>[]];
    if (function_exists('sokna_module_enabled') && sokna_module_enabled('marketing')) {
        $marketing['campaign'] = function_exists('active_campaign') ? active_campaign() : null;
        if(is_array($marketing['campaign'])&&!empty($marketing['campaign']['image_path']))$mediaPaths[]=(string)$marketing['campaign']['image_path'];
        if (setting_bool('events_enabled', true)) {
            $rows = $pdo->query("SELECT id,title,short_description,description,image_path,starts_at,ends_at,venue,capacity,registration_type,registration_value,fee_amount,admission_text,featured,sort_order
                FROM events WHERE active=1 ORDER BY featured DESC,starts_at,sort_order,id LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if (function_exists('event_lifecycle_status') && !in_array(event_lifecycle_status($row), ['upcoming','live'], true)) continue;
                if (!empty($row['image_path'])) $mediaPaths[] = (string)$row['image_path'];
                $marketing['events'][] = $row;
                if (count($marketing['events']) >= 30) break;
            }
        }
    }

    $theme = [
        'cafe_name'=>setting('cafe_name','سکنا'),
        'primary_color'=>valid_hex_color(setting('primary_color','#365b4c'),'#365b4c'),
        'accent_color'=>valid_hex_color(setting('accent_color','#b85c38'),'#b85c38'),
        'background_color'=>valid_hex_color(setting('background_color','#f7f3ec'),'#f7f3ec'),
        'logo_path'=>$logoPath,'menu_theme'=>menu_theme(),'menu_font'=>menu_font(),
        'menu_density'=>menu_density(),'menu_layout'=>menu_layout(),
        'seo_description'=>setting('seo_description','منوی کافه و رویدادهای سکنا'),
        'social_footer_enabled'=>setting_bool('social_footer_enabled',true),
        'social_links'=>setting_bool('social_footer_enabled',true) ? social_links() : [],
        'accommodation_enabled'=>setting_bool('accommodation_enabled',true),
        'accommodation_site_url'=>setting('accommodation_site_url',''),
        'accommodation_card_title'=>setting('accommodation_card_title','خانه سکنا رو هم می‌شناسی؟'),
        'accommodation_card_text'=>setting('accommodation_card_text','اقامت، گشت‌وگذار و تجربه غرب هرمزگان'),
        'instagram_cafe_url'=>setting('instagram_cafe_url',''),
        'post_order_instagram_enabled'=>setting_bool('post_order_instagram_enabled',true),
        'public_about_enabled'=>setting_bool('public_about_enabled',true),
        'analytics_enabled'=>false,
    ];

    return [
        'format'=>SOKNA_GUEST_SNAPSHOT_FORMAT,
        'menus'=>array_map(static fn(array $menu): array => [
            'menu_key'=>(string)$menu['menu_key'],'name'=>(string)$menu['name'],'sort_order'=>(int)$menu['sort_order'],
        ], $menus),
        'catalogs'=>$catalogs,'tables'=>$tables,'theme'=>$theme,
        'messages'=>public_customer_messages(),'marketing'=>$marketing,
        'features'=>[
            'public_waiter_call_enabled'=>setting_bool('public_waiter_call_enabled',false),
            'events_enabled'=>setting_bool('events_enabled',true),
            'campaigns_enabled'=>setting_bool('campaigns_enabled',true),
            'marketing_module'=>function_exists('sokna_module_enabled') ? sokna_module_enabled('marketing') : false,
            'reporting_module'=>function_exists('sokna_module_enabled') ? sokna_module_enabled('reporting') : false,
        ],
        '_media_paths'=>$mediaPaths,
    ];
}

function guest_publish_build_package(PDO $pdo): array
{
    $snapshot = guest_publish_build_snapshot($pdo);
    $mediaPaths = $snapshot['_media_paths'] ?? [];
    unset($snapshot['_media_paths']);
    $manifest = guest_publish_collect_media(is_array($mediaPaths) ? $mediaPaths : []);
    $content = ['format'=>SOKNA_GUEST_SNAPSHOT_FORMAT,'snapshot'=>$snapshot,'media_manifest'=>$manifest];
    $hash = sokna_relay_request_hash($content);
    return [
        'revision_id'=>'guest-' . substr($hash, 0, 32),'content_hash'=>$hash,'generated_at'=>gmdate('c'),
        'snapshot'=>$snapshot,'media_manifest'=>$manifest,
    ];
}

function guest_publish_upload_media(array $manifest): array
{
    $uploaded = [];
    foreach ($manifest as $sourcePath => $meta) {
        $candidate = guest_publish_media_candidate((string)$sourcePath);
        if (!$candidate || !hash_equals((string)$meta['sha256'], (string)$candidate['sha256'])) {
            throw new RuntimeException('فایل رسانه هنگام انتشار تغییر کرده است: ' . $sourcePath);
        }
        $bytes = file_get_contents((string)$candidate['absolute_path']);
        if (!is_string($bytes)) throw new RuntimeException('خواندن فایل رسانه انجام نشد: ' . $sourcePath);
        $result = sokna_relay_http('POST', '/api/v1/local/guest/media.php', [
            'sha256'=>(string)$candidate['sha256'],'mime'=>(string)$candidate['mime'],
            'extension'=>(string)$candidate['extension'],'size'=>(int)$candidate['size'],
            'content_base64'=>base64_encode($bytes),
        ]);
        if (empty($result['ok'])) throw new RuntimeException('آپلود رسانه ناموفق بود: ' . (string)($result['error'] ?? 'unknown'));
        $uploaded[] = (string)$candidate['sha256'];
    }
    return $uploaded;
}

function guest_publish_now(PDO $pdo): array
{
    $bind = sokna_relay_http('POST', '/api/v1/local/bind.php', ['display_name'=>setting('cafe_name','SOKNA Cafe')]);
    if (empty($bind['ok'])) throw new RuntimeException('اتصال Public آماده نیست: ' . (string)($bind['error'] ?? 'unknown'));
    $package = guest_publish_build_package($pdo);
    guest_publish_upload_media($package['media_manifest']);
    $result = sokna_relay_http('POST', '/api/v1/local/guest/publish.php', $package);
    if (empty($result['ok'])) throw new RuntimeException('انتشار منوی مهمان ناموفق بود: ' . (string)($result['error'] ?? 'unknown'));
    return $result + ['revision_id'=>$package['revision_id'],'content_hash'=>$package['content_hash']];
}

function guest_availability_payload(PDO $pdo): array
{
    $rows = $pdo->query("SELECT id,item_code,available,preparation_station FROM items WHERE active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $acceptance = order_acceptance_states();
    $items = [];
    foreach ($rows as $row) {
        $scope = preparation_area_for_station((string)$row['preparation_station']);
        $blocked = !$acceptance['cafe'] || !($acceptance[$scope] ?? true);
        $items[(string)(int)$row['id']] = [
            'item_code'=>(string)($row['item_code'] ?? ''),'available'=>(int)$row['available'] === 1,
            'order_available'=>(int)$row['available'] === 1 && !$blocked,
            'blocked_scope'=>!$acceptance['cafe'] ? 'cafe' : ($blocked ? $scope : null),
        ];
    }
    $payload = [
        'generated_at'=>gmdate('c'),'order_acceptance'=>$acceptance,
        'waiter_enabled_table'=>setting_bool('waiter_call_enabled',true),
        'waiter_enabled_public'=>setting_bool('public_waiter_call_enabled',false),
        'station_states'=>station_busy_states(),
        'station_state_hash'=>station_state_hash(),
        'order_acceptance_messages'=>[
            'cafe'=>order_acceptance_message('cafe'),
            'kitchen'=>order_acceptance_message('kitchen'),
            'bar'=>order_acceptance_message('bar'),
        ],
        'items'=>$items,
    ];
    $payload['version']=hash('sha256', sokna_relay_canonical_json($payload));
    return $payload;
}
