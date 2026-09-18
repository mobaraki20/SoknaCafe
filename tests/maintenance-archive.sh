#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP_BASE="${TMPDIR:-/tmp}/sokna-maintenance-test-$$"
trap 'rm -f "${TMP_BASE}.tar" "${TMP_BASE}.tar.gz"' EXIT
cd "$ROOT"
php -d phar.readonly=0 -r '
$base=$argv[1];
$db="SET FOREIGN_KEY_CHECKS=0;\n-- CAFE-STMT --\nSET FOREIGN_KEY_CHECKS=1;\n-- CAFE-STMT --\n";
$appKey=str_repeat("a",64);
$entries=[
 ["path"=>"database/database.sql","size"=>strlen($db),"sha256"=>hash("sha256",$db)],
 ["path"=>"system/app.key","size"=>strlen($appKey),"sha256"=>hash("sha256",$appKey)]
];
$index=json_encode($entries,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$manifest=["format"=>"sokna-backup-v3","type"=>"backup","version"=>trim((string)file_get_contents("VERSION.txt")),"created_at"=>date(DATE_ATOM),"database_sha256"=>hash("sha256",$db),"files_index_sha256"=>hash("sha256",$index),"entry_count"=>count($entries),"portable_app_identity"=>true,"app_identity_fingerprint"=>substr(hash("sha256",$appKey),0,16)];
$p=new PharData($base.".tar");
$p->addFromString("database/database.sql",$db);
$p->addFromString("system/app.key",$appKey);
$p->addFromString("files.json",$index);
$p->addFromString("manifest.json",json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
$compressed=$p->compress(Phar::GZ); unset($compressed,$p);
' "$TMP_BASE"
php -r '
require "includes/functions.php"; require "includes/maintenance.php";
$result=maintenance_validate_archive($argv[1]);
if(($result["manifest"]["format"]??"")!=="sokna-backup-v3") exit(1);
if(strlen((string)($result["app_key"]??""))!==64) exit(1);
echo "Maintenance archive validation passed.\n";
' "${TMP_BASE}.tar.gz"
