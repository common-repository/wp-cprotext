(function(undefined){
    "use strict";
    function cptxIE8Fix(){
      var h=document.getElementsByTagName("head")[0];
      var s=document.createElement("style");
      s.type="text/css";
      s.styleSheet.cssText=":before,:after{content:none !important;}";
      h.appendChild(s);
      setTimeout(function(){
          h.removeChild(s);},
        0);
    }
    var oldOnLoad=window.onload;
    if(typeof window.onload !== 'function'){
      window.onload=cptxIE8Fix;
    }else{
      window.onload=function(){
        if(oldOnLoad){
          oldOnLoad();
        }
        cptxIE8Fix();
    };
  }
}());
