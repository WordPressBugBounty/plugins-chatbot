// (function($) {
//     window.qcld_openrouter = {
//         open_ai_message_callback: function(msg) {
//             if(wp_chatbot_obj.openrouter_enabled == "1"){
//                 console.log('openrouterx');
                
//                 var data = {
//                     'action': 'openrouter_response',
//                     'name': globalwpw.hasNameCookie,
//                     'keyword': msg,
//                     'nonce': wp_chatbot_obj.ajax_nonce
//                 };

//                 console.log(data);

//                 var $container = $(globalwpw.settings.messageContainer);
//                 var $lastChild = $(globalwpw.settings.messageLastChild);

//                 if($lastChild.find('.wp-chatbot-comment-loader').length==0){
//                     $container.append(wpwKits.botPreloader());
//                 }

//                 $.ajax({
//                     url: wp_chatbot_obj.ajax_url,
//                     type: 'POST',
//                     data: data,
//                     success: function(res) {
//                         var json = $.parseJSON(res);
//                         if(json.status == 'success'){
//                             var serviceOffer = wpwKits.randomMsg(globalwpw.settings.obj.support_option_again);
//                             setTimeout(function(){
//                                 wpwMsg.single(json.message);
//                                 if(globalwpw.settings.obj.disable_repeatative != 1){
//                                     setTimeout(function(){
//                                         var serviceOffer = wpwKits.randomMsg(globalwpw.settings.obj.support_option_again);
                                    
//                                         if((globalwpw.settings.obj.qcld_disable_start_menu != "1")){
//                                             if(typeof(globalwpw.wildcards) != 'undefined' && (globalwpw.wildcards != '')){
//                                                 wpwMsg.double_nobg(serviceOffer, globalwpw.wildcards);
//                                             }else{
//                                                 wpwMsg.single(serviceOffer);
//                                             }
//                                         }
//                                     }, globalwpw.settings.preLoadingTime)
//                                 }else{
//                                     setTimeout(function(){
//                                         if((globalwpw.settings.obj.qcld_disable_repited_startmenu != "1")){
//                                             wpwMsg.single_nobg('<span class="qcld-chatbot-wildcard qcld_back_to_start" data-wildcart="back">' + wpwKits.randomMsg(globalwpw.settings.obj.back_to_start) + '</span>');
//                                         }
//                                     }, globalwpw.settings.preLoadingTime*2);
//                                 }
//                             }, globalwpw.settings.preLoadingTime)
//                         }
//                     }
//                 });
//             }
//         }
//     };
// })(jQuery);