/**
 * name: f1gooat
 * version: v1.0.0
 * description: Website & codebase by Arno Ramon - Skerm
 * author: Skerm <info@skerm.be>
 * homepage: https://skerm.be
 */
import{g as r}from"./index-DDlvirwQ.js";const o=document.body.dataset.currentPlayer;function d(e){o&&e.dataset.playerId===o&&e.querySelector(".js-player--name").insertAdjacentHTML("beforeend",'<span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-green-500 text-white rounded-full">You</span>');const n=e.querySelector(".js-player--info"),a=e.querySelector(".js-player--details"),s=e.querySelector(".js-player--arrow");let t=!1;n.addEventListener("click",l=>{l.target.closest("a")||(t=!t,r.to(a,{height:t?"auto":0,opacity:t?1:0,duration:.8}),r.to(s,{rotation:t?180:0,duration:.4}))})}export{d as playerRaceByRace};
