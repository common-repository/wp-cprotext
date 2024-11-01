/*globals cprotext */
(function($,undefined){
    "use strict";

    var cprotextPopupOnLoad=function(){
      var popup=$("#cptxPopUp").detach();
      var cptxDialog=$("<div id='cptxDialog' class='popup'></div>");
      cptxDialog.dialog({
          autoOpen: false,
          title:"CPROTEXT",
          resizeable: false,
          modal:true,
          minWidth: 600,
          buttons:[{
              text: cprotext.L10N.closeButton,
              click: function(){
                cptxDialog.dialog("close");
                cptxDialog.remove();
                cptxDialog="";
              }
          }]
      });
      cptxDialog.html(popup.html());
      cptxDialog.dialog('open');
    };

    var oldOnLoad=window.onload;
    if(typeof window.onload !== 'function'){
      window.onload=cprotextPopupOnLoad;
    } else {
      window.onload=function(){
        if(oldOnLoad){
          oldOnLoad();
        }
        cprotextPopupOnLoad();
      };
    }
}(jQuery));
