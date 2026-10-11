import re,json
from playwright.sync_api import sync_playwright
BASE='http://127.0.0.1:8091'
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    for user in ['ja-local-qa','local_cashier']:
        context=browser.new_context(viewport={'width':1440,'height':1000})
        page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
        page.goto(BASE+'/?ja_management=1');page.locator('#user_login').fill(user);page.locator('#user_pass').fill('local-fixture-only-2026');page.locator('#wp-submit').click()
        page.goto(BASE+'/?ja_management=1');page.get_by_role('heading',name='Balcão de vendas').wait_for(timeout=30000)
        if user=='local_cashier':
            assert page.get_by_role('button',name='Produtos e estoque',exact=True).count()==0
            page.locator('#open-form [name="opening"]').fill('30.00');page.get_by_role('button',name='Abrir caixa',exact=True).click();page.locator('#open-form').wait_for(state='detached',timeout=15000)
            page.locator('#search-form input').fill('7890000000001');page.locator('#search-form button[type=submit]').click();page.locator('[data-qty]').wait_for(timeout=15000)
            page.locator('#sale-form [name="tender"]').fill('25.00');page.locator('#sale-form [name="confirmed"]').check();page.get_by_role('button',name='Concluir venda',exact=True).click()
            page.get_by_role('heading',name=re.compile('Venda #.* concluída')).wait_for(timeout=15000);page.get_by_role('button',name='Fechar',exact=True).click()
            forbidden=page.evaluate("""async()=>{const r=await fetch(JA_MANAGEMENT.api+'stock',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':JA_MANAGEMENT.nonce},body:JSON.stringify({id:10,mode:'set',quantity:100,reason:'blocked',token:crypto.randomUUID()})});return r.status;}""");assert forbidden==403
            csrf=page.evaluate("""async()=>{const r=await fetch(JA_MANAGEMENT.api+'open',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({opening:'0',token:crypto.randomUUID()})});return r.status;}""");assert csrf in [401,403]
            page.goto(BASE+'/wp-admin/edit.php?post_type=product');assert 'ja_management=1' in page.url;page.get_by_role('heading',name='Balcão de vendas').wait_for()
            page.screenshot(path='/tmp/ja-gestao-caixa.png',full_page=True);page.set_viewport_size({'width':390,'height':844});page.screenshot(path='/tmp/ja-gestao-mobile.png',full_page=True);assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
            context.set_offline(True);page.locator('#network').wait_for(state='visible');assert page.locator('#sale-form button[type=submit]').is_disabled();context.set_offline(False)
        else:
            for tab in ['Produtos e estoque','Compras e fornecedores','Resultados da loja','Equipe e acessos','Histórico de operações','Pedidos online']:
                page.get_by_role('button',name=tab,exact=True).click();page.get_by_role('heading',name=tab,exact=True).wait_for()
            page.get_by_role('button',name='Resultados da loja',exact=True).click();page.screenshot(path='/tmp/ja-gestao-relatorios.png',full_page=True)
            csv=page.evaluate("""async()=>{const r=await fetch(JA_MANAGEMENT.api+'export',{headers:{'X-WP-Nonce':JA_MANAGEMENT.nonce}});return {status:r.status,type:r.headers.get('content-type'),text:await r.text()};}""");assert csv['status']==200 and csv['type'].startswith('text/csv') and '7890000000001' in csv['text']
        assert not errors,errors
        print('PASS browser:',user,'role permissions, real data, no JavaScript errors')
        context.close()
    browser.close()
