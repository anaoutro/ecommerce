#!/usr/bin/env python3
"""Gera o tema WordPress e os pacotes instaláveis a partir da vitrine."""
from pathlib import Path
import json, re, shutil, zipfile, csv

root = Path(__file__).resolve().parents[1]
dist = root / 'dist'
theme = root / 'wordpress/ja-eletronicos-theme'
source = root / 'wordpress/import-source'
release = root / 'release'
release.mkdir(exist_ok=True)
source.mkdir(exist_ok=True)

for name in ['app.js', 'carousel.js', 'commerce.js', 'carousel.css', 'fonts.css']:
    shutil.copyfile(dist / name, theme / name)
shutil.copyfile(dist / 'style.css', theme / 'design.css')
assets = theme / 'assets'
assets.mkdir(exist_ok=True)
shutil.copytree(dist / 'assets/fonts', assets / 'fonts', dirs_exist_ok=True)
for name in ['logo.webp', 'favicon.svg', 'product-placeholder.svg']:
    shutil.copyfile(dist / 'assets' / name, assets / name)
for file in (dist / 'assets').glob('hero-*.webp'):
    shutil.copyfile(file, assets / file.name)

text = (dist / 'catalog.js').read_text()
products = json.loads(text[text.index('['):].rstrip(';\n\r '))
(source / 'catalog.json').write_text(json.dumps(products, ensure_ascii=False, separators=(',', ':')))
preview = products[:]
available = set(re.findall(r"'JA-\d{4}'", (dist / 'app.js').read_text()))
(assets / 'products').mkdir(exist_ok=True)
for p in preview:
    image = dist / p['image']
    if "'" + p['id'] + "'" in available and image.is_file():
        shutil.copyfile(image, assets / 'products' / image.name)
    else:
        p['image'] = 'assets/product-placeholder.svg'
(theme / 'preview-catalog.json').write_text(json.dumps(preview, ensure_ascii=False, separators=(',', ':')))

markup = (dist / 'index.html').read_text()
markup = re.sub(r'<title>.*?</title>', '', markup)
markup = re.sub(r'<link[^>]*>', '', markup)
markup = re.sub(r'<script defer[^>]*></script>', '', markup)
markup = markup.replace('<html lang="pt-BR">', '<html <?php language_attributes(); ?>>')
markup = markup.replace('</head>', '<?php wp_head(); ?></head>')
markup = markup.replace('<body>', '<body <?php body_class(); ?>><?php wp_body_open(); ?>')
markup = markup.replace('</body>', '<?php wp_footer(); ?></body>')
markup = re.sub(r'src="(assets/[^"<>]+)"', lambda m: 'src="<?php echo esc_url(get_theme_file_uri(\'' + m[1] + '\')); ?>"', markup)
markup = markup.replace('<footer>', '<?php if (function_exists(\'ja_loja_ready\') && ja_loja_ready()) : ?><footer><div class="container footer-bottom"><span>© <?php echo esc_html(wp_date(\'Y\')); ?> JA Eletrônicos.</span><span>Capas ilustrativas · Frete e condições no checkout.</span><a href="<?php echo esc_url(wc_get_page_permalink(\'myaccount\')); ?>">Minha conta →</a></div></footer><?php else : ?><footer>', 1)
# O primeiro footer é a versão conectada; fechar a condição depois do footer original.
last_footer = markup.rfind('</footer>')
markup = markup[:last_footer] + markup[last_footer:].replace('</footer>', '</footer><?php endif; ?>', 1)
(theme / 'front-page.php').write_text("<?php defined('ABSPATH') || exit; ?>\n" + markup)

with (source / 'produtos-woocommerce.csv').open('w', newline='', encoding='utf-8-sig') as file:
    writer = csv.writer(file)
    writer.writerow(['Type', 'SKU', 'Name', 'Published', 'Visibility in catalog', 'Short description', 'Regular price', 'Categories', 'In stock?', 'Stock', 'Images'])
    originals = json.loads(text[text.index('['):].rstrip(';\n\r '))
    for p in originals:
        writer.writerow(['simple', p['id'], p['name'], -1, 'visible', p.get('description', ''), p['price'], p['category'], 0, 0, 'https://ja-eletronicos.netlify.app/assets/products/' + p['id'] + '.webp'])

for folder, name in [(theme, 'ja-eletronicos-theme.zip'), (root / 'wordpress/ja-loja-core', 'ja-loja-core.zip')]:
    folder.chmod(0o755)
    for path in folder.rglob('*'):
        path.chmod(0o755 if path.is_dir() else 0o644)
    with zipfile.ZipFile(release / name, 'w', zipfile.ZIP_DEFLATED) as archive:
        for path in folder.rglob('*'):
            if path.is_file(): archive.write(path, str(Path(folder.name) / path.relative_to(folder)))
with zipfile.ZipFile(release / 'ja-catalogo-importacao.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
    for path in source.iterdir():
        if path.is_file(): archive.write(path, 'import-source/' + path.name)
    for path in (dist / 'assets/products').glob('*.webp'):
        archive.write(path, 'import-source/images/' + path.name)
print('Pacotes gerados em', release)
