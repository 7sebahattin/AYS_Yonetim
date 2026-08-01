  </div><!-- .page-content -->
</div><!-- .main-wrap -->

<script>
/* ── Bottom Nav More Panel ─────────────────────────────── */
function toggleMorePanel(){
  var p=document.getElementById('bnav-more-panel');
  p.classList.contains('open') ? closeMorePanel() : openMorePanel();
}
function openMorePanel(){
  document.getElementById('bnav-more-panel').classList.add('open');
  document.getElementById('bnav-overlay').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeMorePanel(){
  document.getElementById('bnav-more-panel').classList.remove('open');
  document.getElementById('bnav-overlay').classList.remove('open');
  document.body.style.overflow='';
}

/* ── Flash: otomatik kapat ─────────────────────────────── */
document.querySelectorAll('.flash').forEach(function(el){
  setTimeout(function(){
    el.style.transition='opacity .5s,transform .5s';
    el.style.opacity='0';el.style.transform='translateY(-8px)';
    setTimeout(function(){el.remove();},500);
  },3500);
});

/* ── Silme onay ─────────────────────────────────────────── */
document.querySelectorAll('[data-confirm]').forEach(function(b){
  b.addEventListener('click',function(e){
    if(!confirm(this.dataset.confirm||'Emin misiniz?'))e.preventDefault();
  });
});

/* ── Prevent accidental form double-submit ─────────────── */
document.querySelectorAll('form').forEach(function(f){
  f.addEventListener('submit',function(){
    var b=f.querySelector('button[type=submit]');
    if(b){setTimeout(function(){b.disabled=true;b.style.opacity='.6';},10);}
  });
});
</script>
</body>
</html>
