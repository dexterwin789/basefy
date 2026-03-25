/**
 * PIX Key Input Mask
 * Auto-applies masks based on PIX key type select.
 * Usage: add data-pix-mask-group on a container that holds
 *   select[name="pix_key_type"] and input[name="pix_key"]
 */
(function(){
  function maskCPF(v){
    v = v.replace(/\D/g,'').slice(0,11);
    if(v.length>9) return v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/,'$1.$2.$3-$4');
    if(v.length>6) return v.replace(/(\d{3})(\d{3})(\d{1,3})/,'$1.$2.$3');
    if(v.length>3) return v.replace(/(\d{3})(\d{1,3})/,'$1.$2');
    return v;
  }
  function maskPhone(v){
    v = v.replace(/\D/g,'').slice(0,11);
    if(v.length>6) return v.replace(/(\d{2})(\d{5})(\d{1,4})/,'($1) $2-$3');
    if(v.length>2) return v.replace(/(\d{2})(\d{1,5})/,'($1) $2');
    return v;
  }

  function applyMask(input, type){
    if(type==='cpf'){
      input.setAttribute('maxlength','14');
      input.setAttribute('placeholder','000.000.000-00');
      input.value = maskCPF(input.value);
      input.disabled = false;
    } else if(type==='telefone'){
      input.setAttribute('maxlength','15');
      input.setAttribute('placeholder','(00) 00000-0000');
      input.value = maskPhone(input.value);
      input.disabled = false;
    } else if(type==='email'){
      input.removeAttribute('maxlength');
      input.setAttribute('placeholder','seu@email.com');
      input.disabled = false;
    } else if(type==='aleatoria'){
      input.setAttribute('maxlength','36');
      input.setAttribute('placeholder','xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
      input.disabled = false;
    } else {
      input.removeAttribute('maxlength');
      input.setAttribute('placeholder','Selecione o tipo da chave');
      input.disabled = true;
    }
  }

  function init(){
    document.querySelectorAll('[data-pix-mask-group]').forEach(function(group){
      var sel = group.querySelector('select[name="pix_key_type"]');
      var inp = group.querySelector('input[name="pix_key"]');
      if(!sel || !inp) return;

      sel.addEventListener('change', function(){
        inp.value = '';
        applyMask(inp, sel.value);
      });

      inp.addEventListener('input', function(){
        var t = sel.value;
        if(t==='cpf') inp.value = maskCPF(inp.value);
        else if(t==='telefone') inp.value = maskPhone(inp.value);
      });

      // Init on load
      if(sel.value) { applyMask(inp, sel.value); }
      else { inp.disabled = true; inp.setAttribute('placeholder','Selecione o tipo da chave'); }
    });
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
