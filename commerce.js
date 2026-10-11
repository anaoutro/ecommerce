'use strict';
window.JA_RUNTIME=window.JA_RUNTIME||{mode:'preview'};
window.JA_CHECKOUT=()=>{
 const config=window.JA_RUNTIME;if(config.mode!=='woocommerce')return;
 const action=new URL(config.checkoutAction,location.href);if(action.origin!==location.origin)throw new Error('Checkout deve usar a mesma origem da loja.');
 let list;try{list=JSON.parse(localStorage.getItem('ja-v2-list'))||{}}catch{list={}}
 const selected=Object.entries(list).map(([id,quantity])=>({product:window.JA_CATALOG.find(p=>p.id===id),quantity})).filter(x=>x.product&&Number.isInteger(x.quantity)&&x.quantity>0);
 if(!selected.length)return;
 const form=document.createElement('form');form.method='POST';form.action=action.href;form.hidden=true;
 const values={action:'ja_checkout',ja_nonce:config.checkoutNonce,items:JSON.stringify(selected.map(x=>({id:x.product.woocommerceId,quantity:x.quantity})))};
 Object.entries(values).forEach(([key,value])=>{const input=document.createElement('input');input.type='hidden';input.name=key;input.value=value;form.append(input)});document.body.append(form);form.submit();
};
