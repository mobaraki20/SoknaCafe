from pathlib import Path

def box(locator):
    value = locator.bounding_box()
    if not value:
        raise AssertionError(f'missing geometry for {locator}')
    return value

def vertical_gap(upper, lower):
    a, b = box(upper), box(lower)
    return b['y'] - (a['y'] + a['height'])

def assert_min_vertical_gap(upper, lower, minimum, label):
    gap = vertical_gap(upper, lower)
    if gap + 0.5 < minimum:
        raise AssertionError(f'{label}: vertical gap {gap:.2f}px < {minimum}px')
    return gap

def assert_no_horizontal_overflow(page, label='page'):
    overflow = page.evaluate('() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1')
    if overflow:
        raise AssertionError(f'{label}: horizontal overflow')

def assert_inside(parent, child, label):
    p, c = box(parent), box(child)
    eps = 0.75
    if c['x'] < p['x']-eps or c['y'] < p['y']-eps or c['x']+c['width'] > p['x']+p['width']+eps or c['y']+c['height'] > p['y']+p['height']+eps:
        raise AssertionError(f'{label}: child escaped parent bounds')

def assert_no_pair_overlap(locators, label):
    boxes=[box(x) for x in locators]
    for i,a in enumerate(boxes):
        for j,b in enumerate(boxes[i+1:],i+1):
            x=max(0,min(a['x']+a['width'],b['x']+b['width'])-max(a['x'],b['x']))
            y=max(0,min(a['y']+a['height'],b['y']+b['height'])-max(a['y'],b['y']))
            if x>0.75 and y>0.75:
                raise AssertionError(f'{label}: elements {i} and {j} overlap by {x:.2f}x{y:.2f}px')

def assert_text_not_clipped(page, selector, label):
    bad=page.eval_on_selector_all(selector, '''els => els.map((el,i)=>({i,sw:el.scrollWidth,cw:el.clientWidth,sh:el.scrollHeight,ch:el.clientHeight})).filter(x=>x.sw>x.cw+1 || x.sh>x.ch+1)''')
    if bad:
        raise AssertionError(f'{label}: clipped/overflowing content {bad}')

def maybe_screenshot(page, name):
    import os
    out=os.getenv('SOKNA_VISUAL_ARTIFACT_DIR','').strip()
    if not out:
        return
    path=Path(out); path.mkdir(parents=True,exist_ok=True)
    page.screenshot(path=str(path/f'{name}.png'),full_page=True)
