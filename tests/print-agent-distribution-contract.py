#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
read=lambda p:(ROOT/p).read_text(encoding='utf-8')
printing=read('includes/printing.php')
admin=read('admin/printing.php')

def need(cond,msg):
    if not cond: raise AssertionError(msg)

need(not (ROOT/'print-agent-v6').exists(),'Agent source must not be bundled in Cafe source/release')
need(not list((ROOT/'print-agent').glob('Sokna-Print-Agent-*')),'Agent binary/archive must not be bundled in Cafe source/release')
need((ROOT/'print-agent/v4/api.php').is_file(),'Print API v4 must remain in Cafe')
need("return 'mobaraki20/Pagent';" in printing,'Pagent repository must remain the explicit Agent distribution owner')
need('/repos/\' . print_agent_distribution_repository() . \'/releases/latest' in printing,'latest stable metadata must resolve through GitHub Releases API')
need('print_agent_release_cache_ttl_seconds' in printing and '6 * 3600' in printing,'GitHub metadata must be cached for six hours')
need("'source' => 'fallback'" in printing and "$version = '6.2.2';" in printing,'resolver needs an official stable bootstrap fallback')
need("Sokna-Print-Agent-' . $version . '-Setup.exe" in printing,'Setup asset must be validated by versioned filename')
need("'download_url' => 'https://github.com/' . $repository . '/releases/download/'" in printing,'download URL must be derived from trusted versioned GitHub Release path')
need('latest/download' not in printing,'floating latest/download binary URL is forbidden')
need('cache-stale' in printing,'GitHub outage must fall back to stale last-known-good metadata')
need('print_agent_release_metadata()' in admin,'Admin must use shared dynamic release metadata')
need('print_agent_download_url()' in admin,'Admin download must use shared release resolver')
need('print_agent_package_available' not in admin+printing,'Admin/server must not depend on local Agent package availability')
need('asset(print_agent_' not in admin,'external Agent URL must not be rewritten as a Cafe asset URL')
need(not (ROOT/'print-agent/api.php').exists(),'legacy v3 Agent API must not ship in the clean baseline')
need((ROOT/'docs/PRINT_AGENT_DISTRIBUTION_CONTRACT_FA.md').is_file(),'distribution ownership must be documented')
print('PASS print_agent_distribution_contract dynamic stable GitHub resolver')
