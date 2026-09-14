const CACHE_NAME='showdepremios-static-v2';
const STATIC_PREFIXES=['/assets/'];
const STATIC_FILES=['/manifest.json','/favicon.ico'];

self.addEventListener('install',event=>{event.waitUntil(self.skipWaiting());});
self.addEventListener('activate',event=>{
  event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE_NAME).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));
});

self.addEventListener('fetch',event=>{
  const req=event.request;
  if(req.method!=='GET') return;
  const url=new URL(req.url);
  if(url.origin!==self.location.origin) return;

  const isStatic=STATIC_PREFIXES.some(prefix=>url.pathname.startsWith(prefix))||STATIC_FILES.includes(url.pathname);
  if(!isStatic){
    // Páginas, APIs, pedidos, cartelas, validações e áreas autenticadas nunca vão para Cache Storage.
    return;
  }

  event.respondWith(caches.open(CACHE_NAME).then(async cache=>{
    const cached=await cache.match(req);
    const network=fetch(req).then(res=>{
      if(res.ok && res.type==='basic') cache.put(req,res.clone());
      return res;
    }).catch(()=>cached);
    return cached||network;
  }));
});
