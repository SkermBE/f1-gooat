/**
 * name: f1gooat
 * version: v1.0.0
 * description: Website & codebase by Arno Ramon - Skerm
 * author: Skerm <info@skerm.be>
 * homepage: https://skerm.be
 */
function o(e){const s=document.querySelector(".js-refetch-message"),t=e.querySelector(".js-refetch-icon");e.addEventListener("click",async()=>{const r=e.dataset.url,c=e.dataset.csrf;e.disabled=!0,e.classList.add("opacity-50","pointer-events-none"),t&&t.classList.add("animate-spin"),s.textContent="Fetching results...",s.className="js-refetch-message text-xs text-slate-500",s.classList.remove("hidden");try{const a=await(await fetch(r,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded",Accept:"application/json"},body:new URLSearchParams({CRAFT_CSRF_TOKEN:c})})).json();a.success?(s.textContent="Results queued for update. Reloading...",s.className="js-refetch-message text-xs text-emerald-600",setTimeout(()=>window.location.reload(),2e3)):(s.textContent=a.error||"Something went wrong.",s.className="js-refetch-message text-xs text-red-500",e.disabled=!1,e.classList.remove("opacity-50","pointer-events-none"),t&&t.classList.remove("animate-spin"))}catch{s.textContent="Network error. Please try again.",s.className="js-refetch-message text-xs text-red-500",e.disabled=!1,e.classList.remove("opacity-50","pointer-events-none"),t&&t.classList.remove("animate-spin")}})}export{o as refetchResults};
