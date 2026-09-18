from pathlib import Path
src = (Path(__file__).resolve().parents[1] / 'includes/printing.php').read_text(encoding='utf-8')
errors=[]
if "if (!print_destination_ready($pdo, $destinationKey))" in src:
    errors.append('print_enqueue_job drops jobs when destination is unavailable instead of persisting blocked intent')
if "if (!print_destination_ready($pdo, $key)) continue;" in src:
    errors.append('preparation enqueue skips unavailable destinations, allowing required ticket loss')
if errors:
    print('FAIL required_print_intent')
    [print(' - '+e) for e in errors]
    raise SystemExit(1)
print('PASS required_print_intent')
