/**
 * JsHttpRequest: JavaScript DHTML data loader.
 * (C) 2005 Dmitry Koterov, http:
 * @author Dmitry Koterov 
 * @version 3.34
 */

function JsHttpRequest(){this._construct()}
(function(){
var COUNT=0;
var PENDING={};
var CACHE={};
JsHttpRequest.dataReady=function(id,text,js){
var undef;
var th=PENDING[id];
delete PENDING[id];
if(th){
delete th._xmlReq;
if(th.caching) CACHE[th.hash]=[text,js];
th._dataReady(text,js);
}else if(typeof(th)!=typeof(undef)){
alert("ScriptLoader: unknown pending id: "+id);
}
}
JsHttpRequest.prototype={
onreadystatechange:null,
readyState:0,
responseText:null,
responseXML:null,
status:200,
statusText:"OK",
session_name:"PHPSESSID",
responseJS:null, 
caching:false,
fallbackToScript:false,
_span:null,
_id:null,
_xmlReq:null,
_openArg: null,
_reqHeaders:null,
dummy:function(){},
abort:function(){
if(this._xmlReq) return this._xmlReq.abort();
if(this._span){
this.readyState=0;
if(this.onreadystatechange) this.onreadystatechange();
this._cleanupScript();
}
},
open:function(method,url,asyncFlag,username,password){
this._openArg={
'method':method,
'url':url,
'asyncFlag':asyncFlag,
'username':username!=null ? username : '',
'password':password!=null ? password : ''
};
this._id=null;
this._xmlReq=null;
this._reqHeaders=[];
return true;
},
send:function(content){
var id=(new Date().getTime())+""+COUNT++;
var query=this._hash2query(content);
var url=this._openArg.url;
var sid=this._getSid();
if(sid) url+=(url.indexOf('?')>=0 ? '&' : '?')+this.session_name+"="+this.escape(sid);
var hash=this.hash=url+'?'+query;
if(this.caching&&CACHE[hash]){
var c=CACHE[hash];
this._dataReady(c[0],c[1]);
return false;
}
this._xmlReq=this._obtainXmlReq(id,url);
var hasSetHeader=this._xmlReq&&(window.ActiveXObject||this._xmlReq.setRequestHeader); 
var href, body;
var method=(""+this._openArg.method).toUpperCase();
if(this._xmlReq && hasSetHeader && method=="POST"){
this._openArg.method="POST";
href=url;
body=query;
}else{
if(method!='GET'&&!this.fallbackToScript&&query.length>2000){
throw 'Cannot use XMLHttpRequest nor Microsoft.XMLHTTP for long POST query: object not implemented or disabled in browser.';
}
this._openArg.method="GET";
href=url+(url.indexOf('?')>=0 ? '&' : '?')+query;
body=null;
}
href=href+(href.indexOf('?')>=0 ? '&' : '?')+id;
PENDING[id]=this;
if(this._xmlReq){
var a=this._openArg;
this._xmlReq.open(a.method,href+"-xml",a.asyncFlag,a.username,a.password);
if(hasSetHeader){
for (var i=0;i<this._reqHeaders.length;i++)
this._xmlReq.setRequestHeader(this._reqHeaders[i][0],this._reqHeaders[i][1]);
this._xmlReq.setRequestHeader('Content-Type', 'application/octet-stream');
}
return this._xmlReq.send(body);
}else{
this._obtainScript(id,href);
return true;
}
},
getAllResponseHeaders:function(){
if(this._xmlReq) return this._xmlReq.getAllResponseHeaders();
return '';
},
getResponseHeader:function(label){
if(this._xmlReq) return this._xmlReq.getResponseHeader(label);
return '';
},
setRequestHeader:function(label,value){
this._reqHeaders[this._reqHeaders.length]=[label, value];
},
_construct:function(){},
_dataReady:function(text,js){with(this){
if(text!==null||js!==null){
readyState=4;
responseText=responseXML=text;
responseJS=js;
}else{
readyState=0;
responseText=responseXML=responseJS=null;
}
if(onreadystatechange) onreadystatechange();
_cleanupScript();
}},
_obtainXmlReq:function(id,url){
var p=url.match(new RegExp('^[a-z]+://(.*)', 'i'));
if(p){
var curHost=document.location.host.toLowerCase();
if(p[1].substring(0, curHost.length).toLowerCase()==curHost){
url=p[1].substring(curHost.length, p[1].length);
}else{
return null;
}
}
var req=null;
if(window.XMLHttpRequest){
try{req=new XMLHttpRequest()}catch(e){}
}else if(window.ActiveXObject){
try{req=new ActiveXObject("Microsoft.XMLHTTP")}catch(e){}
if(!req) try{req=new ActiveXObject("Msxml2.XMLHTTP")}catch(e){}
}
if(req){
var th=this;
req.onreadystatechange=function(){ 
var s=req.readyState;
if(s==4){
req.onreadystatechange=th.dummy;
var responseText=req.responseText;
try{
eval(responseText);
}catch(e){
JsHttpRequest.dataReady(id, "JavaScript code generated by backend is invalid!\n"+responseText, null);
}
}else{
th.readyState=s;
if(th.onreadystatechange) th.onreadystatechange() 
}
};
this._id=id;
}
return req;
},
_obtainScript:function(id,href){with(document){
var span=null;
var span=createElement("SPAN");
span.style.display='none';
body.appendChild(span);
span.innerHTML='Text for stupid IE.<s'+'cript></'+'script>';
setTimeout(function(){
var s=span.getElementsByTagName("script")[0];
s.language="JavaScript";
if(s.setAttribute) s.setAttribute('src',href);else s.src=href;
},10);
this._id=id;
this._span=span;
}},
_cleanupScript:function(){
var span=this._span;
if(span){
this._span=null;
setTimeout(function(){
span.parentNode.removeChild(span);
},50);
}
return false;
},
_hash2query:function(content,prefix){
if(prefix==null) prefix="";
var query=[];
if(content instanceof Object){
for(var k in content){
var v=content[k];
if(v==null||((v.constructor||{}).prototype||{})[k]) continue;
var curPrefix=prefix ? prefix+'['+this.escape(k)+']' : this.escape(k);
if(v instanceof Object)
query[query.length]=this._hash2query(v,curPrefix);
else
query[query.length]=curPrefix+"="+this.escape(v);
}
}else{
query=[content];
}
return query.join('&');
},
_getSid:function(){
var m=document.location.search.match(new RegExp('[&?]'+this.session_name+'=([^&?]*)'));
var sid=null;
if(m){
sid=m[1];
}else{
var m=document.cookie.match(new RegExp('(;|^)\\s*'+this.session_name+'=([^;]*)'));
if(m) sid=m[2];
}
return sid;
},
escape:function(s){
return escape(s).replace(new RegExp('\\+','g'), '%2B');
}
}
})();
