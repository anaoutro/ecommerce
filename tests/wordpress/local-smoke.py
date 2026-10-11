from pathlib import Path
import json,re,secrets
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[2];BASE='http://localhost:8090'
password=json.loads((ROOT/'local/.secrets/bootstrap.json').read_text())['password']
def check(value,name):
    if not value:raise AssertionError(name)
    print('PASS:',name)
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    owner=browser.new_context(viewport={'width':1440,'height':1000});page=owner.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(BASE+'/?ja_management=1');page.locator('#user_login').fill('ja-admin');page.locator('#user_pass').fill(password);page.locator('#wp-submit').click();page.goto(BASE+'/?ja_management=1');page.get_by_role('heading',name='Balcão de vendas').wait_for(timeout=30000)
    # Usa a API real, autenticada pela mesma sessão e pelo nonce WordPress.
    def api(action,data=None):
        return page.evaluate('''async ({action,data})=>{const url=new URL(JA_MANAGEMENT.api);if(url.searchParams.has('rest_route'))url.searchParams.set('rest_route',url.searchParams.get('rest_route')+action);else url.pathname+=action;const r=await fetch(url,{method:data?'POST':'GET',headers:{'X-WP-Nonce':JA_MANAGEMENT.nonce,...(data?{'Content-Type':'application/json'}:{})},...(data?{body:JSON.stringify({...data,token:crypto.randomUUID()})}:{})});const result=await r.json();return {status:r.status,data:result};}''',{'action':action,'data':data})
    state=api('state')['data'];check(state['admin'] and len(state['products'])==8,'Instalação nova com oito produtos de referência de amostra');product=state['products'][0]
    check(all(p['stock']==0 for p in state['products']),'Importação não inventa quantidades')
    result=api('stock',{'id':product['id'],'mode':'set','quantity':3,'reason':'Teste automatizado local; não representa contagem real'});check(result['status']==200,'Contagem grava no estoque WooCommerce')
    email='caixa-apresentacao@example.invalid';result=api('employee',{'login':'caixa-apresentacao','email':email,'name':'Caixa de apresentação local'});check(result['status']==200,'Cadastro real de funcionário')
    response=owner.request.get('http://localhost:8025/api/v1/messages').json();messages=response['messages'];message=next(m for m in messages if any(t['Address']==email for t in m['To']))
    mail=owner.request.get('http://localhost:8025/api/v1/message/'+message['ID']).json();link=next(line.strip().strip('<>') for line in mail['Text'].splitlines() if 'wp-login.php' in line and 'key=' in line)
    check(link.startswith(BASE+'/wp-login.php'),'E-mail de definição de senha fica na caixa local')
    employee=browser.new_context(viewport={'width':390,'height':844});staff=employee.new_page();staff.goto(link);employee_password=secrets.token_urlsafe(32);staff.locator('#pass1').fill(employee_password);staff.locator('#wp-submit').click();staff.goto(BASE+'/?ja_management=1');staff.locator('#user_login').fill('caixa-apresentacao');staff.locator('#user_pass').fill(employee_password);staff.locator('#wp-submit').click();staff.get_by_role('heading',name='Balcão de vendas').wait_for(timeout=30000)
    check(staff.get_by_role('button',name='Produtos e estoque',exact=True).count()==0,'Funcionário entra com sua própria senha e vê somente caixa')
    check(staff.evaluate('document.documentElement.scrollWidth<=innerWidth'),'Gestão no celular sem transbordamento')
    # Após a definição, autentica o funcionário com senha gerada exclusivamente para o teste.
    # Nenhuma senha é impressa em logs.
    employee.close()
    # A definição de senha foi exercitada; permissões e venda pelo funcionário já têm teste independente.
    for tab in ['Produtos e estoque','Compras e fornecedores','Resultados da loja','Equipe e acessos','Histórico de operações','Pedidos online']:
        page.get_by_role('button',name=tab,exact=True).click();page.get_by_role('heading',name=tab,exact=True).wait_for()
    check(not errors,'Painel completo sem erros JavaScript')
    page.get_by_role('button',name='Produtos e estoque',exact=True).click();page.screenshot(path=str(ROOT/'tests/results/local-gestao.png'),full_page=True)
    shop=owner.new_page();shop.goto(BASE);shop.wait_for_function('window.JA_CATALOG && window.JA_CATALOG.length===8');check(shop.evaluate('JA_RUNTIME.mode')=='woocommerce','Vitrine usa catálogo do WooCommerce, sem dados de demonstração embutidos');shop.screenshot(path=str(ROOT/'tests/results/local-vitrine.png'),full_page=True)
    browser.close()
