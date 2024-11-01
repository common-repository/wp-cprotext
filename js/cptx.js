/*globals cprotext,tinyMCE */
(function($,undefined){
    "use strict";

    var cprotextOnLoad=function(){
      var token=null;
      var formevent='';

      var cptx=$('#cptx_check');
      var initialTitle='';
      var initialContent='';
      var initialFont='';
      var initialPlh='';
      var initialKw='';

      var fontlist=$('#cptx_sync');
      var clickhandlers=[];

      var cptxDialog='';
      var dialogOptions={
        autoOpen: false,
        title:'CPROTEXT',
        // JqueryUI >= 1.12
        classes: {
          'ui-dialog': 'ui-corner-all cptxDialog',
          'ui-dialog-titlebar': 'ui-corner-all',
        },
        // JqueryUI < 1.12
        dialogClass: 'cptxDialog',
        resizable: false,
        modal: true
      };

      function cptxError(intro, error){
        var text=intro;
        if(error){
          intro+=':<br/><span class="cptxmsg cptxerror">'+error+'</span>';
        }
        cptxDialog.html(text);
        cptxDialog.dialog('option','buttons',[{
              text: cprotext.L10N.okButton,
              click: cptxCloseDialog
        }]);
      }

      function cptxEnableSaveButton(){
        var button=$('#submit');
        if(button.attr('disabled')==='disabled'){
          button.removeAttr('disabled');
        }
      }

      function cptxCloseDialog(){
        cptxDialog.dialog('close');
        cptxDialog.remove();
        cptxDialog='';
      }

      function cptxAddFont(token,wpcptx){
        $('#cptx_settings').ajaxForm({
            beforeSerialize: function(form,options){
              var data={
                'f': 'font',
              };
              data[cprotext.API.NONCE_VAR]=wpcptx;
              data=$.param(data);
              options.url=cprotext.API.URL+data;
              options.type='post';
              form[0].enctype='multipart/form-data';
              $(form[0]).find('input,select,button').
              attr('disabled','disabled');
              $(form[0].cptx_newfont).removeAttr('disabled');
            },
            beforeSend: function(request){
              var progress=$('.progress');
              progress.css('display','inline-block');
              var pVal=0;
              progress.children('.bar').width(pVal);
              progress.children('.percent').html(pVal+'%');

              request.setRequestHeader('Authorization', 'Bearer ' + token);
            },
            uploadProgress: function(e,p,t,pc){
              var progress=$('.progress');
              var pVal=pc+'%';
              progress.children('.bar').width(pVal);
              progress.children('.percent').html(pVal);
            },
            success: function(){
              var progress=$('.progress');
              var pVal='100%';
              progress.children('.bar').width(pVal);
              progress.children('.percent').html(pVal);
            },
            complete: function(xhr){
              $('#cptx_settings *').
              removeAttr('disabled');
              var response=$.parseJSON(xhr.responseText);
              if(response.hasOwnProperty('error')){
                $('.status').html(response.error);
                return;
              }
              $('.status').html(
                cprotext.L10N.fontfile1+' "'+response.data[1]+
                  '" '+cprotext.L10N.fontfile2
              );
              $('#cptx_newfont').val('');

              $('#cptx_font').append('<option value="'+
                  response.data[0]+'">'+
                  response.data[1]+'</option>'
              );
              var options= $('#cptx_font option');
              if($(options[0]).attr('title')){
                $('#cptx_font').empty();
              }
              var list=[];
              list.push('\[');
              var o,len;
              for(o=0,len=options.length;o<len;o++){
                list.push('\["'+
                    $(options[o]).val()+'","'+
                    $(options[o]).html()+'"]');
                list.push(',');
              }
              list.pop();
              list.push('\]');
              $('#cptx_hfontlist').val(list.join(''));
            }
        });

        cptxDialog.dialog('close');
        $('#cptx_settings').submit();

        $('#cptx_settings').ajaxFormUnbind();

        //TODO: remind user to save changes otherwise he'll
        //      have to update the font list next time the
        //      CPROTEXT settings page is reloaded

      }

      function cptxGetFontList(token,wpcptx){
        cptxDialog.html(cprotext.L10N.importSettings);

        var data={
          'f': 'fonts'
        };
        data[cprotext.API.NONCE_VAR]=wpcptx;
        data=$.param(data);

        var response=$.ajax({
            url: cprotext.API.URL,
            data: data,
            dataType: 'jsonp',
            error: function(jqXHR,textStatus,errorThrown){
              cptxError(cprotext.L10N.fontlistFail,textStatus+'/'+errorThrown);
            },
            beforeSend: function(request){
              request.setRequestHeader('Authorization', 'Bearer ' + token);
            },
            success: function(response){
              if(response.hasOwnProperty('error')){
                cptxError(cprotext.L10N.fontlistFail,response.error);
              }else{
                var list=[];
                var defaultFont=$('#cptx_font').val();
                list.push('[');
                  $('#cptx_font').empty();
                  for(var i=0,len=response.fontlist.length;i<len;i++){
                    var selected='';
                    if(response.fontlist[i][0]===defaultFont){
                      selected=' selected="selected" ';
                    }
                    $('#cptx_font').append('<option value="'+
                        response.fontlist[i][0]+'"'+selected+'>'+
                        response.fontlist[i][1]+'</option>'
                    );
                    list.push('["'+response.fontlist[i][0]+'","'+
                        response.fontlist[i][1]+'"]');
                    list.push(',');
                  }
                  list.pop();
                  list.push(']');
                $('#cptx_font, #cptx_newfont').removeAttr('disabled');
                $('#cptx_hfontlist').val(list.join(''));
                $('#cptx_hsync').val('1');
                cptxEnableSaveButton();
              }
              cptxCloseDialog();
            }
        });
      }

      function cptxGetText(token,wpcptx,textId){
        // disable user interaction
        $('.ui-dialog-buttonpane').attr('disabled','disabled').css('visibility','hidden');
        $('.legen p').html(cprotext.L10N.getInit);

        var data={
          'f':'get',
          'tid': textId
        };
        data[cprotext.API.NONCE_VAR]=wpcptx;
        data=$.param(data);

        var response=$.ajax({
            url: cprotext.API.URL+data,
            dataType: 'jsonp',
            beforeSend: function(request){
              request.setRequestHeader('Authorization', 'Bearer ' + token);
            },
            error: function(jqXHR,textStatus,errorThrown){
              $('.ui-dialog-buttonpane').removeAttr('disabled').css('visibility','visible');
              cptxError(cprotext.L10N.getFail,
                textStatus+'<br/>'+cprotext.L10N.cancellation
              );
            },

            success: function(response) {
              $('.ui-dialog-buttonpane').removeAttr('disabled').css('visibility','visible');
              if(response.hasOwnProperty('error')){
                cptxError(cprotext.L10N.getFail,
                    response.error+'<br/>'+cprotext.L10N.cancellation
                );
              }else{
                $('#waitforit').removeClass('dary');
                $('#cptx_contentVer').
                val(response.version);
                $('#cptx_contentCSS').val(response.css);
                $('#cptx_contentHTML').
                val(response.html);
                $('#cptx_contentEOTE').
                val(response.eote);
                $('#cptx_contentEOTS').
                val(response.eots);
                $('#cptx_contentId').val(response.tid);
                cptxDialog.html('<p>'+cprotext.L10N.getSuccess1+'</p><p>'+
                    cprotext.L10N.getSuccess2+'</p>'
                );
                cptxDialog.dialog('option','buttons',[{
                      text: cprotext.L10N.submitButton,
                      click: function(){
                        cptxCloseDialog();
                        $('#publish').off('click.cptx');
                        while(clickhandlers.length>0){
                          $('#publish').on('click',
                            clickhandlers.shift());
                        }
                        if(!$('#cptx_statusChange').val()){
                          $('#cptx_statusChange').val(0);
                        }
                        var changes=$('#cptx_statusChange').val();
                        changes^=cprotext.STATUS.PROCESSING;
                        $('#cptx_statusChange').val(changes);
                        $('#publish').click();
                      }
                }]);
              }
            }
        });
      }

      function cptxCheckStatus(token,wpcptx,textId,next){
        next = typeof next !== 'undefined' ? next:'status';
        var data={
          'f':'status',
          'tid': textId,
          'n': next
        };
        data[cprotext.API.NONCE_VAR]=wpcptx;
        data=$.param(data);

        var response=$.ajax({
            url: cprotext.API.URL+data,
            dataType: 'jsonp',
            beforeSend: function(request){
              request.setRequestHeader('Authorization', 'Bearer ' + token);
            },
            error: function(jqXHR,textStatus,errorThrown){
              cptxError(cprotext.L10N.submitFail,
                  textStatus+'<br/>'+cprotext.L10N.cancellation
              );
            },

            success: function(response) {
              var string;
              if(response.hasOwnProperty('error')){
                cptxError(cprotext.L10N.statusFail,
                  response.error+'<br/>'+cprotext.L10N.cancellation
                );
                cptxDialog.html(string);
                cptxDialog.dialog('option','buttons',[{
                      text: cprotext.L10N.okButton,
                      click: cptxCloseDialog
                }]);
              }else{
                var status=cprotext.L10N.statusUnknown;
                if(response.status===''){
                  status=cprotext.L10N.statusDone;
                }else{
                  switch(response.status[0]){
                  case -1:
                    status=cprotext.L10N.failed+' => ';
                    switch(response.status[1][0]){
                    case 0: status+=cprotext.L10N.statusError0; break;
                    case 1: status+=cprotext.L10N.statusError1; break;
                    case 2: status+=cprotext.L10N.statusError2; break;
                    case 3: status+=response.status[1][4]+
                        ' '+cprotext.L10N.statusError3;break;
                    case 4:
                      if(response.status[1][5] === 'eot'){
                        status+=cprotext.L10N.statusError41+
                          ' '+response.status[1][4];
                      }else{
                        status+=cprotext.L10N.statusError42+
                          ' '+response.status[1][4];
                      }
                      break;
                    case 5:
                      if(response.status[1][5] === 'eot'){
                        status+=cprotext.L10N.statusError51;
                      }else{
                        status+=cprotext.L10N.statusError52;
                      }
                      break;
                    case 6: status+=cprotext.L10N.statusError6; break;
                    case 7: status+=cprotext.L10N.statusError7; break;
                    }
                    break;
                  case -2:
                    var squareBracket={'opened':'[','closed':']'};
                    status=cprotext.L10N.processing+'<br/>'+
                      squareBracket.opened+' '+cprotext.L10N.step+' ';
                    switch(response.status[1]){
                    case 1: status+='1/3: '+cprotext.L10N.statusProcess1; break;
                    case 2: status+='2/3: '+cprotext.L10N.statusProcess2; break;
                    case 3: status+='3/3: '+cprotext.L10N.statusProcess3; break;
                    }
                    status+=' '+squareBracket.closed;
                    break;
                  case 0:
                    status=cprotext.L10N.statusWaiting;
                    break;
                  default:
                    status=cprotext.L10N.statusQueueing+': '+
                      response.status[1][4]+' '+cprotext.L10N.statusRemaining;
                    break;
                  }
                }

                var statusContent=$('.status');
                if(!statusContent.length){
                  string='<p></p>'+
                    '<div class="legen dary" id="waitforit">'+
                    '<p class="status">'+
                    cprotext.L10N.processing+
                    '</p></div>';
                  cptxDialog.html(string);
                  cptxDialog.dialog('option','buttons',[{
                        text: cprotext.L10N.returnButton,
                        click: function(){
                          cptxCloseDialog();
                          $('#publish').off('click.cptx');
                          while(clickhandlers.length>0){
                            $('#publish').on('click',
                              clickhandlers.shift());
                          }
                          if($('#cptx_contentId').val().charAt(0)==='W'){
                            window.location=$('#wp-admin-bar-view-site>a')[0].href;
                          }else{
                            var changes=$('#cptx_statusChange').val();
                            if(!(changes & cprotext.STATUS.PUBLISHED) &&
                            (changes & cprotext.STATUS.CHANGED) &&
                            (changes & cprotext.STATUS.CPROTEXTED)
                            ){
                              $('#save-post').click();
                            }else{
                              $('#publish').click();
                            }
                          }
                        }
                  }]);
                  cptxDialog.dialog('open');
                }
                statusContent.html(status);
              }
            },

            complete: function(jqXHR,textStatus) {
              // Schedule the next request when the current one's complete
              if(textStatus!='success')
                return;
              if(jqXHR.responseJSON.status !== ''){
                setTimeout(function(){
                  cptxCheckStatus(jqXHR.responseJSON.token,jqXHR.responseJSON.wpcptx,
                    textId);
                }, 2000);
              }else{
                switch(next){
                case 'status':
                  cptxCheckStatus(jqXHR.responseJSON.token,jqXHR.responseJSON.wpcptx,
                    textId,'get');
                  break;
                case 'get':
                  if(jqXHR.responseJSON.pwyw){
                    cptxDialog.html('<p>'+cprotext.L10N.pwyw+'</p>');
                    cptxDialog.dialog('option','buttons',[{
                          text: 'ok',
                          click: function(){
                            cptxCloseDialog();
                            $('#publish').off('click.cptx');
                            while(clickhandlers.length>0){
                              $('#publish').on('click',
                                clickhandlers.shift());
                            }
                            $('#cptx_statusChange').val(0);
                            if(
                              !$('#cptx_contentId').val() ||
                              $('#cptx_contentId').val().charAt(0)==='W'
                            ){
                              $('#save-post').click();
                            }else{
                              window.location=
                                $('#wp-admin-bar-view-site>a')[0].href;
                            }
                          }
                    }]);
                  }else{
                    cptxGetText(jqXHR.responseJSON.token,jqXHR.responseJSON.wpcptx,
                      textId);
                  }
                  break;
                }
              }
            }
        });
      }

      function cptxSubmitText(token,wpcptx,update){
        update=typeof update !== 'undefined' ? update:false;

        var data={
          'n': 'status'
        };
        data[cprotext.API.NONCE_VAR]=wpcptx;

        var postData={
        };

        if(!update){
          data.f='submit';
        }else{
          data.f='update';
          data.tid=$('#cptx_contentId').val();
        }

        var changes=$('#cptx_statusChange').val();
        if(!update ||
          (changes & cprotext.STATUS.UPDATE_TITLE)
        ){
          postData.ti= $('#title').val();
        }
        if(!update ||
          (changes & cprotext.STATUS.UPDATE_CONTENT)
        ){
          postData.c=$('#content').html();
          if($('#wp-content-wrap').hasClass('tmce-active')){
            postData.c=tinyMCE.activeEditor.getContent();
          }else{
            postData.c=$('#content').val();
          }
        }

        if(!update ||
          (changes & cprotext.STATUS.UPDATE_PLH)
        ){
          postData.plh= $('#cptx_plh').val();
        }
        if(!update ||
          (changes & cprotext.STATUS.UPDATE_FONT)
        ){
          data.ft= $('#cptx_font').val();
        }

        data=$.param(data);

        var response=$.ajax({
            cptxFunc: update?'update':'submit',
            url: cprotext.API.URL+data,
            type: 'POST',
            data: postData,
            dataType: 'jsonp',
            beforeSend: function(request){
              request.setRequestHeader('Authorization', 'Bearer ' + token);
            },
            error: function(jqXHR,textStatus,errorThrown){
              cptxError(cprotext.L10N[this.cptxFunc+'Fail'],
                  jqXHR.responseJSON+'<br/>'+ cprotext.L10N.cancellation
              );
            },
            success: function(response){
              cptxDialog.dialog('close');
              if(response.hasOwnProperty('error')){
                cptxError(cprotext.L10N[this.cptxFunc+'Fail'],
                    response.error+'<br/>'+cprotext.L10N.cancellation
                );
                cptxDialog.dialog('open');
              }else{
                $('#cptx_contentId').val(response.tid);

                var changes=$('#cptx_statusChange').val();
                if(update &&
                  (changes & cprotext.STATUS.WPUPDATES) &&
                  !(changes & cprotext.STATUS.UPDATE_CONTENT) &&
                  !(changes & cprotext.STATUS.UPDATE_FONT) &&
                  !(changes & cprotext.STATUS.UPDATE_PLH)
                ){
                  // the only change is the title, which does not require
                  // a feedback from the cprotext site
                  changes^=cprotext.STATUS.PROCESSING;
                  $('#cptx_statusChange').val(changes);
                  cptxCloseDialog();
                  $('#publish').off('click.cptx');
                  while(clickhandlers.length>0){
                    $('#publish').on('click',
                      clickhandlers.shift());
                  }
                  $('#publish').click();
                }

                cptxCheckStatus(response.token,response.wpcptx,
                  response.tid);
              }
            }
        });
      }

      function cptxCheckCredits(token,wpcptx){
        // get current credits:
        //    send token + getCredit
        //    get value in cptx_credits
        var data={
          'f': 'credits',
        };
        data[cprotext.API.NONCE_VAR]=wpcptx;

        var changes=$('#cptx_statusChange').val();
        if(
          $('#cptx_contentId').val() === '' ||
          (changes & cprotext.STATUS.UPDATE_CONTENT)
        ){
          data.n='submit';
        }else{
          data.n='update';
        }

        data=$.param(data);

        var response=$.ajax({
            url: cprotext.API.URL,
            data: data,
            dataType: 'jsonp',
            error: function(jqXHR,textStatus,errorThrown){
              cptxError(cprotext.L10N.creditsFail,cprotext.L10N.cancellation);
            },
            beforeSend: function(request){
              request.setRequestHeader('Authorization', 'Bearer ' + token);
            },
            success: function(response){
              cptxDialog.dialog('close');
              if(response.hasOwnProperty('error')){
                cptxError(cprotext.L10N.creditsFail);
              }else{
                if(
                  $('#cptx_contentId').val() === '' ||
                  ($('#cptx_statusChange').val()&cprotext.STATUS.UPDATE_CONTENT)
                ){
                  cptxSubmitText(response.token,response.wpcptx);
                }else{
                  cptxSubmitText(response.token,response.wpcptx,true);
                }
              }
              cptxDialog.dialog('open');
            }
        });
      }

      function cptxMain(text,noreturn){
        if($('#cptx_authtok').length && $('#cptx_authtok').val().length &&
        (!cprotext.API.TOKEN.length ||
          cprotext.API.TOKEN !== $('#cptx_hauthtok').val()
        )){
          cprotext.API.TOKEN=$('#cptx_authtok').val();
        }
        cptxDialog.html('');
        cptxDialog.dialog($.extend({},dialogOptions,{
              buttons:[
                {
                  text: cprotext.L10N.cancelButton,
                  click: function(){
                    token=null;
                    if(noreturn){
                      window.location=$('#wp-admin-bar-view-site>a')[0].href;
                    }
                    cptxCloseDialog();
                  }
                }
              ]
        }));
        cptxDialog.html('<span class="wait">'+cprotext.L10N.authentication+'</span>');

        var data={
          'f': 'token'
        };

        data[cprotext.API.NONCE_VAR]=cprotext.API.INITIAL_NONCE;

        if(fontlist.length){
          if(formevent==='cptx_sync'){
            data.n='fonts';
          }else if(formevent==='cptx_newfont'){
            data.n='font';
          }
        }else if($('#cptx_contentId').val().charAt(0)==='W'){
          data.n='status';
        }else{
          data.n='credits';
        }

        data=$.param(data);
        var response=$.ajax({
            url: cprotext.API.URL,
            data: data,
            beforeSend: function(request){
              if(cprotext.API.TOKEN.length){
                request.setRequestHeader('Authorization', 'Bearer ' +
                    cprotext.API.TOKEN);
              }
            },
            dataType: 'jsonp',
            error: function(jqXHR,textStatus,errorThrown){
              cptxError(cprotext.L10N.identifyFail,textStatus+'/'+errorThrown);
            },
            success: function(response){
              if(response.hasOwnProperty('error')){
                cptxError(cprotext.L10N.identifyFail,response.error);
              }else{
                if(fontlist.length){
                  if(formevent==='cptx_sync'){
                    cptxGetFontList(response.token,response.wpcptx);
                  }else if(formevent==='cptx_newfont'){
                    cptxAddFont(response.token,response.wpcptx);
                  }
                }else if($('#cptx_contentId').val().charAt(0)=='W'){
                  cptxCheckStatus(response.token,response.wpcptx,
                    $('#cptx_contentId').val().substr(1));
                }else{
                  cptxCheckCredits(response.token,response.wpcptx);
                }
              }
            }
        });
      }

      function cptxChangeListPopUp(changes){
        var changesList='';

        if(changes & cprotext.STATUS.UPDATE_CONTENT){
          changesList+='<li> - '+cprotext.L10N.commitUpdateContent+'</li>';
        }
        if(changes & cprotext.STATUS.UPDATE_TITLE){
          changesList+='<li> - '+cprotext.L10N.commitUpdateTitle+'</li>';
        }
        if(changes & cprotext.STATUS.UPDATE_FONT){
          changesList+='<li> - '+cprotext.L10N.commitUpdateFont+'</li>';
        }
        if(changes & cprotext.STATUS.UPDATE_PLH){
          changesList+='<li> - '+cprotext.L10N.commitUpdatePlh+'</li>';
        }

        if(changesList !== ''){
          $('#cptx_statusChange').val(changes);
          cptxMain(
            '<div>'+cprotext.L10N.authenticationRequired+'</div>'+
              '<ul>'+changesList+'</ul>'
          );
        }else{
          changes^=cprotext.STATUS.PROCESSING;
          $('#cptx_statusChange').val(changes);
          $('#publish').off('click.cptx');
          while(clickhandlers.length>0){
            $('#publish').on('click',
              clickhandlers.shift());
          }
          $('#publish').click();
        }
      }

      function handler(e){
        formevent=e.target.id;

        if(fontlist.length){
          cptxDialog=$('<div id="cptxDialog"></div>');
          cptxMain();
        }

        if(cptx.length &&
          (cptx.prop('checked') || cptx[0].getAttribute('checked'))
        ){
          var changes=cprotext.STATUS.CPROTEXTED | cprotext.STATUS.PROCESSING;

          if($('#post_status>option[selected="selected"]').val()!=='publish'){
            // previous status was 'draft' or 'pending'
            changes|=cprotext.STATUS.NOT_PUBLISHED;
            if($('#publish').length && $('#publish').attr('name')==='publish'){
              // desired status is 'publish'
              changes|=cprotext.STATUS.CHANGED;
            }else{
              // should not happen
              changes|=cprotext.STATUS.NOT_CHANGED;
            }
          }else{
            // previous status was 'publish'
            changes|=cprotext.STATUS.PUBLISHED;
            if($('#post_status>option:selected').val()==='publish'){
              // desired status is still 'published'
              changes|=cprotext.STATUS.NOT_CHANGED;
            }else{
              // desired status is 'draft' or 'pending'
              changes|=cprotext.STATUS.CHANGED;
            }
          }


          var wpcontent='';
          if($('#wp-content-wrap').hasClass('tmce-active')){
            wpcontent=tinyMCE.activeEditor.getContent();
          }else{
            wpcontent=$('#content').val();
          }

          if(initialContent!=wpcontent){
            changes|=cprotext.STATUS.UPDATE_CONTENT;
          }
          if(initialTitle!==$('#title').val()){
            changes|=cprotext.STATUS.UPDATE_TITLE;
          }
          if(initialFont!==$('#cptx_font').val()){
            changes|=cprotext.STATUS.UPDATE_FONT;
          }
          if(initialPlh!==$('#cptx_plh').val()){
            changes|=cprotext.STATUS.UPDATE_PLH;
          }
          if(initialKw!==$('#cptx_kw').val()){
            changes|=cprotext.STATUS.UPDATE_KW;
          }


          if($('#cptxed').length){
            cptxed=$('#cptxed').val();
          }

          cptxDialog=$('<div id="cptxDialog"></div>');

          var string='';

          switch(changes&(cprotext.STATUS.PUBLISHED|cprotext.STATUS.CHANGED)){
          case (cprotext.STATUS.NOT_PUBLISHED | cprotext.STATUS.NOT_CHANGED):
            cptxDialog.html(
              '<div>'+cprotext.L10N.bugReportRequired+'</div>');
            cptxDialog.dialog($.extend({},dialogOptions,{
                  buttons:[{
                      text:cprotext.L10N.closeButton,
                      click: cptxCloseDialog
                  }]
            }));
            break;

          case (cprotext.STATUS.NOT_PUBLISHED | cprotext.STATUS.CHANGED):
            // Draft or Pending => Publish
            // Orginal cprotext status can not have been checked,
            // but it can already have existing CPROTEXT data.
            // if we are here it means that it is now checked
            // CPROTEXTion required
            if(!cptxed){
              // todo: check if content is empty,
              // if so refuse request for protection
              $('#cptx_statusChange').val(changes);
              cptxMain();
            }else{
              cptxChangeListPopUp(changes);
            }
            break;

          case (cprotext.STATUS.PUBLISHED | cprotext.STATUS.NOT_CHANGED):
            // Publish => Publish
            if(cptx[0].getAttribute('checked')==='checked'){
              // CPROTEXT check box was checked
              if(cptx.prop('checked')){
                // CPROTEXT check box is still checked
                cptxChangeListPopUp(changes);
              }else{
                // CPROTEXT check box is now not checked
                // if text was not modified, it must be unprotected
                // if text was modified, the previous revision must be kept as
                // is while the new revision goes through vanilla wordpress
                // processing
                changes^=cprotext.STATUS.CPROTEXTED;
                changes^=cprotext.STATUS.PROCESSING;
                $('#cptx_statusChange').val(changes);
                $('#publish').off('click.cptx');
                while(clickhandlers.length>0){
                  $('#publish').on('click',
                    clickhandlers.shift());
                }
                $('#publish').click();
              }
            }else{
              // CPROTEXT check box was not checked
              if(cptx.prop('checked')){
                // CPROTEXT check box is now checked
                // protect the new or current revision
                if(!cptxed){
                  $('#cptx_statusChange').val(changes);
                  cptxMain();
                }else{
                  cptxChangeListPopUp(changes);
                }
              }else{
                // CPROTEXT check box is still not checked
                // impossible !
                changes^=cprotext.STATUS.CPROTEXTED;
                changes^=cprotext.STATUS.PROCESSING;
                $('#cptx_statusChange').val(changes);
                cptxDialog.html(
                  '<div>'+cprotext.L10N.bugReportRequired+'</div>');
                cptxDialog.dialog($.extend({},dialogOptions,{
                      buttons:[{
                          text:cprotext.L10N.closeButton,
                          click: cptxCloseDialog
                      }]
                }));
              }
            }
            break;

          case (cprotext.STATUS.PUBLISHED | cprotext.STATUS.CHANGED):
            // Publish => Draft or Pending
            // whatever the current status of CPROTEXT check box,
            // there should be no need for CPROTEXTion
            if(cptx[0].getAttribute('checked')==='checked'){
              // CPROTEXT check box was checked
              if(cptx.prop('checked')){
                // CPROTEXT check box is still checked
                // warn user that:
                // - previous revision will still be associated with CPROTEXT data
                // - new revision won't be protected
                string='<div>'+cprotext.L10N.unpublished;
                if(changes & cprotext.STATUS.WPUPDATES){
                  string+=cprotext.L10N.detachedFromRevision;
                }else{
                  string+=cprotext.L10N.attachedToRevision;
                }
                string+='</div>';
                cptxDialog.html(string);
                cptxDialog.dialog($.extend({},dialogOptions,{
                      buttons:[{
                          text:cprotext.L10N.closeButton,
                          click:  function(){
                            cptxCloseDialog();
                            $('#cptx_statusChange').val(changes);
                            $('#publish').off('click.cptx');
                            while(clickhandlers.length>0){
                              $('#publish').on('click',
                                clickhandlers.shift());
                            }
                            $('#publish').click();
                          }
                      }]
                }));
              }else{
                // CPROTEXT check box is now not checked
                // nothing special to do:
                // if user unchecked the box himself, he knows what he is doing
                // if user unckecked the box by mistake, he will just have to
                // check it again
                changes^=cprotext.STATUS.CPROTEXTED;
                changes^=cprotext.STATUS.PROCESSING;

                // unprotect previous revision (but don't delete CPROTEXT data)
                // new revision goes through vanilla wordpress process
                $('#cptx_statusChange').val(changes);
                $('#publish').off('click.cptx');
                while(clickhandlers.length>0){
                  $('#publish').on('click',
                    clickhandlers.shift());
                }
                $('#publish').click();
              }
            }else{
              // CPROTEXT check box was not checked
              if(cptx.prop('checked')){
                // CPROTEXT check box is now checked
                // why would user want to protect a draft or a pending text ?
                // warn then go through vanilla wordpress process
                string='<div>'+cprotext.L10N.uselessProtection+'</div>';
                cptxDialog.html(string);
                cptxDialog.dialog($.extend({},dialogOptions,{
                      buttons:[{
                          text:cprotext.L10N.closeButton,
                          click:  function(){
                            cptxCloseDialog();
                            changes^=cprotext.STATUS.PROCESSING;
                            $('#cptx_statusChange').val(changes);
                            $('#publish').off('click.cptx');
                            while(clickhandlers.length>0){
                              $('#publish').on('click',
                                clickhandlers.shift());
                            }
                            $('#publish').click();
                          }
                      }]
                }));
              }else{
                // CPROTEXT check box is still not checked
                // impossible !
                changes^=cprotext.STATUS.CPROTEXTED;
                changes^=cprotext.STATUS.PROCESSING;
                $('#cptx_statusChange').val(changes);
                string='<div>'+cprotext.L10N.bugReportRequired+'</div>';
                cptxDialog.html(string);
                cptxDialog.dialog($.extend({},dialogOptions,{
                      buttons:[{
                          text:cprotext.L10N.closeButton,
                          click: cptxCloseDialog
                      }]
                }));
              }
            }
            break;
          }
        }
        if(cptxDialog!==''){
          cptxDialog.dialog('open');
          e.stopImmediatePropagation();
          return false;
        }
      }

      function cptxToggleAccount(event){
        if(!$('#cptx_authtok').val().length){
          $('#cptx_sync, #cptx_font, #cptx_newfont').attr('disabled','disabled');
          $('#cptx_authtok').addClass('redborders');
        }else{
          if($('#cptx_authtok').val() === $('#cptx_hauthtok').val()){
            $('#cptx_sync, #cptx_font, #cptx_newfont').removeAttr('disabled');
            $('#cptx_authtok').removeClass('redborders');
            if($('#cptx_hsync').val()==='1'){
              $('#cptx_sync').removeClass('redborders');
            }
          }else{
            $('#cptx_sync').removeAttr('disabled');
            $('#cptx_font, #cptx_newfont').attr('disabled','disabled');
            $('#cptx_authtok, #cptx_sync').addClass('redborders');
            $('#cptx_hsync').val('0');
          }
        }
        if($('#cptx_hsync').val()==='0'){
            $('#cptx_font, #cptx_newfont').attr('disabled','disabled');
            $('#cptx_sync').addClass('redborders');
        }
      }

      if(cptx.length || fontlist.length){
        $('#cptx_check').on('click',function(){
          if($(this).attr('checked')==='checked'){
            $('#cptx_font, #cptx_font option').
            removeAttr('disabled');
            $('#cptx_kw').removeAttr('disabled');
            $('#cptx_plh').removeAttr('disabled');
            $('#cptxed').removeAttr('disabled');
            $('#cptx_contentVer').removeAttr('disabled');
            $('#cptx_contentCSS').removeAttr('disabled');
            $('#cptx_contentHTML').removeAttr('disabled');
            $('#cptx_contentEOTE').removeAttr('disabled');
            $('#cptx_contentEOTS').removeAttr('disabled');
            $('#cptx_contentId').removeAttr('disabled');
            $('#cptx_statusChange').removeAttr('disabled');

            initialFont=$('#cptx_font').val();
            initialPlh=$('#cptx_plh').val();
            initialKw=$('#cptx_kw').val();
          }else{
            $('#cptx_font, #cptx_font option').
            attr('disabled','disabled');
            $('#cptx_kw').attr('disabled','disabled');
            $('#cptx_plh').attr('disabled','disabled');
            $('#cptxed').attr('disabled','disabled');
            $('#cptx_contentVer').attr('disabled','disabled');
            $('#cptx_contentCSS').attr('disabled','disabled');
            $('#cptx_contentHTML').attr('disabled','disabled');
            $('#cptx_contentEOTE').attr('disabled','disabled');
            $('#cptx_contentEOTS').attr('disabled','disabled');
            $('#cptx_contentId').attr('disabled','disabled');
            $('#cptx_statusChange').attr('disabled','disabled');
          }
        });

        if(cptx.length && $('#cptx_contentId').val().charAt(0)==='W'){
          cptxDialog=$('<div id="cptxDialog"></div>');
          cptxMain('<p>'+cprotext.L10N.resume+'</p>',true);
          cptxDialog.dialog('open');
        }

        if($('#publish').length){
          var events=$._data($('#publish')[0],'events');
          for(var i=0,len=events.click.length;i<len;i++){
            clickhandlers.push(events.click[i].handler);
          }
          $('#publish').off('click');
        }else{
          cptxToggleAccount();
          $('#cptx_settings').on('change',
            'input,select,textarea',cptxEnableSaveButton);
        }

        $('#publish, #cptx_sync').on('click.cptx',handler);
        $('#cptx_newfont').on('change.cptx',handler);
        $('#cptx_authtok').on('input.cptx, propertychange.cptx',cptxToggleAccount);
      }

      if(cptx.length){
        initialTitle=$('#title').val();
        if($('#wp-content-wrap').hasClass('tmce-active')){
          initialContent=tinyMCE.activeEditor.getContent();
        }else{
          initialContent=$('#content').val();
        }
        initialFont=$('#cptx_font').val();
        initialPlh=$('#cptx_plh').val();
        initialKw=$('#cptx_kw').val();
      }
    };

    var oldOnLoad=window.onload;
    if(typeof window.onload !== 'function'){
      window.onload=cprotextOnLoad;
    } else {
      window.onload=function(){
        if(oldOnLoad){
          oldOnLoad();
        }
        cprotextOnLoad();
      };
    }
}(jQuery));
