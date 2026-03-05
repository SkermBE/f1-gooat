/**
 * name: f1gooat
 * version: v1.0.0
 * description: Website & codebase by Arno Ramon - Skerm
 * author: Skerm <info@skerm.be>
 * homepage: https://skerm.be
 */
import{g as r}from"./index-DDlvirwQ.js";function l(e){const o=e.querySelector(".js-player--info"),a=e.querySelector(".js-player--details"),n=e.querySelector(".js-player--arrow");let t=!1;o.addEventListener("click",i=>{i.target.closest("a")||(t=!t,r.to(a,{height:t?"auto":0,opacity:t?1:0,duration:.8}),r.to(n,{rotation:t?180:0,duration:.4}))})}export{l as playerRaceByRace};
