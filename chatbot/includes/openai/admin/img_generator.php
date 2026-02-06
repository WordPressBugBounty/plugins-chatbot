<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpchatbot_pro_professional_init,$wpchatbot_pro_master_init;
if((isset($wpchatbot_pro_master_init) && $wpchatbot_pro_master_init->is_valid()) || (isset($wpchatbot_pro_professional_init) && $wpchatbot_pro_professional_init->is_valid()) || (function_exists('get_openaiaddon_valid_license') && get_openaiaddon_valid_license())){
?>
<style>
    .image-grid {
        display: flex;
        flex-wrap: wrap;
    }

    .image-item {
        margin: 10px;
        width: 256;
        height: 256;
        background-size: cover;
        box-shadow: 0px 0px 10px #ccc;
    }

    .select-element {
        margin: 10px;
    }

    .button-element {
        background-color: #4169E1;
        /* this is the blue color */
        color: white;
        padding: 12px 20px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-size: 14px;
    }

    .button-element:hover {
        background-color: #6495ED;
        /* this is a slightly lighter blue color on hover */
        box-shadow: 0px 0px 10px #B0C4DE;
        /* this is a subtle shadow on hover */
        transform: translateY(-2px);
        /* this is a subtle upward movement on hover */
    }


    textarea {
        width: 100%;
        padding: 12px 20px;
        box-sizing: border-box;
        border: 2px solid #ccc;
        border-radius: 4px;
        background-color: #f8f8f8;
        resize: none;
    }
.qcld_seo_grid_form{
    grid-template-columns: repeat(3,1fr);
    grid-column-gap: 20px;
    grid-row-gap: 20px;
    display: grid;
    grid-template-rows: auto auto;
    margin-top: 20px;

}
.qcld_seo_grid_form #wpai_preview_title{
    font-size: 20px;
    padding: 1px 12px;
}
.qcld_seo_grid_form_1{
    grid-column: span 1/span 1;
}
.qcld_seo_grid_form_2{
    grid-column: span 2/span 1;
}
.qcld_seo-collapse{}
.qcld_seo-collapse:last-of-type{
    border-bottom: 1px solid #ccc;
}
.qcld_seo-collapse-title span{
    display: inline-block;
    margin-right: 5px;
}
.qcld_seo-collapse-title{
    padding: 10px;
    background: #fff;
    border-top: 1px solid #ccc;
    border-left: 1px solid #ccc;
    border-right: 1px solid #ccc;
    font-size: 14px;
    display: flex;
    align-items: center;
    cursor: pointer;
    font-weight: bold;
}
.qcld_seo-collapse-active .qcld_seo-collapse-title{}
.qcld_seo-collapse-content{
    display: none;
    background: #f1f1f1;
    padding: 10px;
    border-top: 1px solid #ccc;
    border-left: 1px solid #ccc;
    border-right: 1px solid #ccc;
}
.qcld_seo-collapse-active .qcld_seo-collapse-content{
    display: block;
}
.qcld_seo-collapse-content .qcld_seo-form-label{
    display: inline-block;
    width: 50%;
}
.qcld_seo-collapse-content select,.qcld_seo-collapse-content input[type=text],.qcld_seo-collapse-content input[type=url]{
    display: inline-block!important;
    width: 48%!important;
}
@media(max-width: 480px){
    .qcld_seo-grid{
        grid-template-columns: repeat(1,1fr);
        grid-column-gap: 10px;
        grid-row-gap: 10px;
    }
    .qcld_seo-grid-1{}
    .qcld_seo-grid-2{
        grid-column: span 1/span 1;
    }
    .qcld_seo-grid-5{
        grid-column: span 1/span 1;
    }
    .qcld_seo-grid-6{
        grid-column: span 1/span 1;
    }
}
</style>

<div class="wrap fs-section">
    
    <div id="poststuff">
        <div id="fs_account">
            
            <div class="qcld_seo_grid_form" id="qcld_seo-post-form">
                <div class="qcld_seo_grid_form_2">
                    <form class="qcld_seo-single-content-form" method="post">

                    
                    <div class="mb-5">
                        <label for="prompt"><?php esc_html_e('Prompt', 'chatbot'); ?>:</label>
                        <textarea name="prompt" id="prompt" rows="2" cols="50"></textarea>
                        <button class="button-element qcld_botopenai_generate_image" name="generate"><?php esc_html_e( 'Generate', 'chatbot' ); ?></button>
                    
                    </div>
                    <div class="mb-5">
                        <div id="qcld_seo-tab-generated-text">
                        </div>
                    </div>
                </div>
                <div class="qcld_seo_grid_form_1">
                    <div class="qcld_seo-collapse qcld_seo-collapse-active">
                        <div class="qcld_seo-collapse-title"><span>-</span><?php esc_html_e('Settings', 'chatbot'); ?></div>
                        <div class="qcld_seo-collapse-content">
                            <div class="mb-5">
                                <label for="artist" class="qcld_seo-form-label"><?php esc_html_e('Artist:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="artist" id="artist">
                                    <option value="Salvador Dalí" selected=""><?php esc_html_e('Salvador Dalí', 'chatbot'); ?></option>
                                    <option value="Leonardo da Vinci"><?php esc_html_e('Leonardo da Vinci', 'chatbot'); ?></option>
                                    <option value="Michelangelo"><?php esc_html_e('Michelangelo', 'chatbot'); ?></option>
                                    <option value="Rembrandt"><?php esc_html_e('Rembrandt', 'chatbot'); ?></option>
                                    <option value="Van Gogh"><?php esc_html_e('Van Gogh', 'chatbot'); ?></option>
                                    <option value="Monet"><?php esc_html_e('Monet', 'chatbot'); ?></option>
                                    <option value="Vermeer"><?php esc_html_e('Vermeer', 'chatbot'); ?></option>
                                    <option value="Johannes Vermeer"><?php esc_html_e('Johannes Vermeer', 'chatbot'); ?></option>
                                    <option value="Raphael"><?php esc_html_e('Raphael', 'chatbot'); ?></option>
                                    <option value="Titian"><?php esc_html_e('Titian', 'chatbot'); ?></option>
                                    <option value="Degas"><?php esc_html_e('Degas', 'chatbot'); ?></option>
                                    <option value="Edgar Degas"><?php esc_html_e('Edgar Degas', 'chatbot'); ?></option>
                                    <option value="El Greco"><?php esc_html_e('El Greco', 'chatbot'); ?></option>
                                    <option value="Cézanne"><?php esc_html_e('Cézanne', 'chatbot'); ?></option>
                                    <option value="Paul Cézanne"><?php esc_html_e('Paul Cézanne', 'chatbot'); ?></option>
                                    <option value="Caravaggio"><?php esc_html_e('Caravaggio', 'chatbot'); ?></option>
                                    <option value="Gustav Klimt"><?php esc_html_e('Gustav Klimt', 'chatbot'); ?></option>
                                    <option value="Henri Matisse"><?php esc_html_e('Henri Matisse', 'chatbot'); ?></option>
                                    <option value="Pablo Picasso"><?php esc_html_e('Pablo Picasso', 'chatbot'); ?></option>
                                    <option value="Diego Velázquez"><?php esc_html_e('Diego Velázquez', 'chatbot'); ?></option>
                                    <option value="Sandro Botticelli"><?php esc_html_e('Sandro Botticelli', 'chatbot'); ?></option>
                                    <option value="Jan van Eyck"><?php esc_html_e('Jan van Eyck', 'chatbot'); ?></option>
                                    <option value="Albrecht Dürer"><?php esc_html_e('Albrecht Dürer', 'chatbot'); ?></option>
                                    <option value="Canaletto"><?php esc_html_e('Canaletto', 'chatbot'); ?></option>
                                    <option value="Frida Kahlo"><?php esc_html_e('Frida Kahlo', 'chatbot'); ?></option>
                                    <option value="Eugene Delacroix"><?php esc_html_e('Eugene Delacroix', 'chatbot'); ?></option>
                                    <option value="Gustav Courbet"><?php esc_html_e('Gustav Courbet', 'chatbot'); ?></option>
                                    <option value="John Singer Sargent"><?php esc_html_e('John Singer Sargent', 'chatbot'); ?></option>
                                    <option value="Georges Seurat"><?php esc_html_e('Georges Seurat', 'chatbot'); ?></option>
                                    <option value="Alfred Sisley"><?php esc_html_e('Alfred Sisley', 'chatbot'); ?></option>
                                    <option value="Pierre-Auguste Renoir"><?php esc_html_e('Pierre-Auguste Renoir', 'chatbot'); ?></option>
                                    <option value="Tintoretto"><?php esc_html_e('Tintoretto', 'chatbot'); ?></option>
                                    <option value="Frederic Edwin Church"><?php esc_html_e('Frederic Edwin Church', 'chatbot'); ?></option>
                                    <option value="John Everett Millais"><?php esc_html_e('John Everett Millais', 'chatbot'); ?></option>
                                    <option value="JMW Turner"><?php esc_html_e('JMW Turner', 'chatbot'); ?></option>
                                    <option value="None"><?php esc_html_e('None', 'chatbot'); ?></option>
                                </select>
                            </div>
                            <div class="mb-5">
                                <label for="art_style" class="qcld_seo-form-label"><?php esc_html_e('Style:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="art_style" id="art_style">
                                    <option value="Surrealism" selected=""><?php esc_html_e('Surrealism', 'chatbot'); ?></option>
                                    <option value="Early Renaissance"><?php esc_html_e('Early Renaissance', 'chatbot'); ?></option>
                                    <option value="Abstract"><?php esc_html_e('Abstract', 'chatbot'); ?></option>
                                    <option value="Abstract Expressionism"><?php esc_html_e('Abstract Expressionism', 'chatbot'); ?></option>
                                    <option value="Action Painting"><?php esc_html_e('Action Painting', 'chatbot'); ?></option>
                                    <option value="Art Deco"><?php esc_html_e('Art Deco', 'chatbot'); ?></option>
                                    <option value="Art Nouveau"><?php esc_html_e('Art Nouveau', 'chatbot'); ?></option>
                                    <option value="Baroque"><?php esc_html_e('Baroque', 'chatbot'); ?></option>
                                    <option value="Cubism"><?php esc_html_e('Cubism', 'chatbot'); ?></option>
                                    <option value="Digital Art"><?php esc_html_e('Digital Art', 'chatbot'); ?></option>
                                    <option value="Expressionism"><?php esc_html_e('Expressionism', 'chatbot'); ?></option>
                                    <option value="Fauvism"><?php esc_html_e('Fauvism', 'chatbot'); ?></option>
                                    <option value="High Renaissance"><?php esc_html_e('High Renaissance', 'chatbot'); ?></option>
                                    <option value="Impressionism"><?php esc_html_e('Impressionism', 'chatbot'); ?></option>
                                    <option value="Mannerism"><?php esc_html_e('Mannerism', 'chatbot'); ?></option>
                                    <option value="Minimalism"><?php esc_html_e('Minimalism', 'chatbot'); ?></option>
                                    <option value="Naïve Art"><?php esc_html_e('Naïve Art', 'chatbot'); ?></option>
                                    <option value="Northern Renaissance"><?php esc_html_e('Northern Renaissance', 'chatbot'); ?></option>
                                    <option value="Pop Art"><?php esc_html_e('Pop Art', 'chatbot'); ?></option>
                                    <option value="Post-Impressionism"><?php esc_html_e('Post-Impressionism', 'chatbot'); ?></option>
                                    <option value="Realism"><?php esc_html_e('Realism', 'chatbot'); ?></option>
                                    <option value="Rococo"><?php esc_html_e('Rococo', 'chatbot'); ?></option>
                                    <option value="Romanticism"><?php esc_html_e('Romanticism', 'chatbot'); ?></option>
                                    <option value="Symbolism"><?php esc_html_e('Symbolism', 'chatbot'); ?></option>
                                    <option value="Ukiyo-e"><?php esc_html_e('Ukiyo-e', 'chatbot'); ?></option>
                                    <option value="None"><?php esc_html_e('None', 'chatbot'); ?></option>
                                </select>
                            </div>
                            <div class="mb-5">
                                <label for="photography_style" class="qcld_seo-form-label"><?php esc_html_e('Photography:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="photography_style" id="photography_style">
                                    <option value="Portrait" selected=""><?php esc_html_e('Portrait', 'chatbot'); ?></option>
                                    <option value="Landscape"><?php esc_html_e('Landscape', 'chatbot'); ?></option>
                                    <option value="Street"><?php esc_html_e('Street', 'chatbot'); ?></option>
                                    <option value="Macro"><?php esc_html_e('Macro', 'chatbot'); ?></option>
                                    <option value="Abstract"><?php esc_html_e('Abstract', 'chatbot'); ?></option>
                                    <option value="Fine art"><?php esc_html_e('Fine art', 'chatbot'); ?></option>
                                    <option value="Black and white"><?php esc_html_e('Black and white', 'chatbot'); ?></option>
                                    <option value="Night"><?php esc_html_e('Night', 'chatbot'); ?></option>
                                    <option value="Sports"><?php esc_html_e('Sports', 'chatbot'); ?></option>
                                    <option value="Fashion"><?php esc_html_e('Fashion', 'chatbot'); ?></option>
                                    <option value="Wildlife"><?php esc_html_e('Wildlife', 'chatbot'); ?></option>
                                    <option value="Nature"><?php esc_html_e('Nature', 'chatbot'); ?></option>
                                    <option value="Travel"><?php esc_html_e('Travel', 'chatbot'); ?></option>
                                    <option value="Documentary"><?php esc_html_e('Documentary', 'chatbot'); ?></option>
                                    <option value="Food"><?php esc_html_e('Food', 'chatbot'); ?></option>
                                    <option value="Architecture"><?php esc_html_e('Architecture', 'chatbot'); ?></option>
                                    <option value="Industrial"><?php esc_html_e('Industrial', 'chatbot'); ?></option>
                                    <option value="Conceptual"><?php esc_html_e('Conceptual', 'chatbot'); ?></option>
                                    <option value="Candid"><?php esc_html_e('Candid', 'chatbot'); ?></option>
                                    <option value="Underwater"><?php esc_html_e('Underwater', 'chatbot'); ?></option>
                                    <option value="None"><?php esc_html_e('None', 'chatbot'); ?></option>
                                </select>
                            </div>
                            <div class="mb-5">
                                <label for="lighting" class="qcld_seo-form-label"><?php esc_html_e('Lighting:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="lighting" id="lighting">
                                    <option value="Ambient" selected><?php esc_html_e('Ambient', 'chatbot'); ?></option>
                                    <option value="Artificial light"><?php esc_html_e('Artificial light', 'chatbot'); ?></option>
                                    <option value="Backlight"><?php esc_html_e('Backlight', 'chatbot'); ?></option>
                                    <option value="Black light"><?php esc_html_e('Black light', 'chatbot'); ?></option>
                                    <option value="Blue hour"><?php esc_html_e('Blue hour', 'chatbot'); ?></option>
                                    <option value="Candle light"><?php esc_html_e('Candle light', 'chatbot'); ?></option>
                                    <option value="Chiaroscuro"><?php esc_html_e('Chiaroscuro', 'chatbot'); ?></option>
                                    <option value="Cloudy"><?php esc_html_e('Cloudy', 'chatbot'); ?></option>
                                    <option value="Color gels"><?php esc_html_e('Color gels', 'chatbot'); ?></option>
                                    <option value="Continuous light"><?php esc_html_e('Continuous light', 'chatbot'); ?></option>
                                    <option value="Contre-jour"><?php esc_html_e('Contre-jour', 'chatbot'); ?></option>
                                    <option value="Direct light"><?php esc_html_e('Direct light', 'chatbot'); ?></option>
                                    <option value="Direct sunlight"><?php esc_html_e('Direct sunlight', 'chatbot'); ?></option>
                                    <option value="Diffused light"><?php esc_html_e('Diffused light', 'chatbot'); ?></option>
                                    <option value="Firelight"><?php esc_html_e('Firelight', 'chatbot'); ?></option>
                                    <option value="Flash"><?php esc_html_e('Flash', 'chatbot'); ?></option>
                                    <option value="Flat light"><?php esc_html_e('Flat light', 'chatbot'); ?></option>
                                    <option value="Fluorescent"><?php esc_html_e('Fluorescent', 'chatbot'); ?></option>
                                    <option value="Fog"><?php esc_html_e('Fog', 'chatbot'); ?></option>
                                    <option value="Front light"><?php esc_html_e('Front light', 'chatbot'); ?></option>
                                    <option value="Golden hour"><?php esc_html_e('Golden hour', 'chatbot'); ?></option>
                                    <option value="Hard light"><?php esc_html_e('Hard light', 'chatbot'); ?></option>
                                    <option value="Soft light"><?php esc_html_e('Soft light', 'chatbot'); ?></option>
                                    <option value="Rim light"><?php esc_html_e('Rim light', 'chatbot'); ?></option>
                                    <option value="Backlight"><?php esc_html_e('Backlight', 'chatbot'); ?></option>
                                    <option value="Silhouette"><?php esc_html_e('Silhouette', 'chatbot'); ?></option>
                                    <option value="Natural light"><?php esc_html_e('Natural light', 'chatbot'); ?></option>
                                    <option value="Studio light"><?php esc_html_e('Studio light', 'chatbot'); ?></option>
                                    <option value="Flash"><?php esc_html_e('Flash', 'chatbot'); ?></option>
                                    <option value="Continuous light"><?php esc_html_e('Continuous light', 'chatbot'); ?></option>
                                    <option value="High key"><?php esc_html_e('High key', 'chatbot'); ?></option>
                                    <option value="Low key"><?php esc_html_e('Low key', 'chatbot'); ?></option>
                                    <option value="Golden hour"><?php esc_html_e('Golden hour', 'chatbot'); ?></option>
                                    <option value="Blue hour"><?php esc_html_e('Blue hour', 'chatbot'); ?></option>
                                    <option value="Diffused light"><?php esc_html_e('Diffused light', 'chatbot'); ?></option>
                                    <option value="Reflected light"><?php esc_html_e('Reflected light', 'chatbot'); ?></option>
                                    <option value="Shaded light"><?php esc_html_e('Shaded light', 'chatbot'); ?></option>
                                    <option value="Side light"><?php esc_html_e('Side light', 'chatbot'); ?></option>
                                    <option value="Direct light"><?php esc_html_e('Direct light', 'chatbot'); ?></option>
                                    <option value="Artificial light"><?php esc_html_e('Artificial light', 'chatbot'); ?></option>
                                    <option value="Moonlight"><?php esc_html_e('Moonlight', 'chatbot'); ?></option>
                                    <option value="None"><?php esc_html_e('None', 'chatbot'); ?></option>
                                </select>

                            </div>
                            <div class="mb-5">
                                <label for="subject" class="qcld_seo-form-label"><?php esc_html_e('Subject:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="subject" id="subject">
                                    <option value="Landscapes" selected><?php esc_html_e('Landscapes', 'chatbot'); ?></option>
                                    <option value="People"><?php esc_html_e('People', 'chatbot'); ?></option>
                                    <option value="Animals"><?php esc_html_e('Animals', 'chatbot'); ?></option>
                                    <option value="Food"><?php esc_html_e('Food', 'chatbot'); ?></option>
                                    <option value="Cars"><?php esc_html_e('Cars', 'chatbot'); ?></option>
                                    <option value="Architecture"><?php esc_html_e('Architecture', 'chatbot'); ?></option>
                                    <option value="Flowers"><?php esc_html_e('Flowers', 'chatbot'); ?></option>
                                    <option value="Still life"><?php esc_html_e('Still life', 'chatbot'); ?></option>
                                    <option value="Portrait"><?php esc_html_e('Portrait', 'chatbot'); ?></option>
                                    <option value="Cityscapes"><?php esc_html_e('Cityscapes', 'chatbot'); ?></option>
                                    <option value="Seascapes"><?php esc_html_e('Seascapes', 'chatbot'); ?></option>
                                    <option value="Nature"><?php esc_html_e('Nature', 'chatbot'); ?></option>
                                    <option value="Action"><?php esc_html_e('Action', 'chatbot'); ?></option>
                                    <option value="Events"><?php esc_html_e('Events', 'chatbot'); ?></option>
                                    <option value="Street"><?php esc_html_e('Street', 'chatbot'); ?></option>
                                    <option value="Abstract"><?php esc_html_e('Abstract', 'chatbot'); ?></option>
                                    <option value="Candid"><?php esc_html_e('Candid', 'chatbot'); ?></option>
                                    <option value="Underwater"><?php esc_html_e('Underwater', 'chatbot'); ?></option>
                                    <option value="Night"><?php esc_html_e('Night', 'chatbot'); ?></option>
                                    <option value="Wildlife"><?php esc_html_e('Wildlife', 'chatbot'); ?></option>
                                    <option value="None"><?php esc_html_e('None', 'chatbot'); ?></option>
                                </select>
                            </div>
                            <div class="mb-5">
                                <label for="camera_settings" class="qcld_seo-form-label"><?php esc_html_e('Camera:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="camera_settings" id="camera_settings">
                                    <option value="Aperture" selected><?php esc_html_e('Aperture', 'chatbot'); ?></option>
                                    <option value="Shutter speed"><?php esc_html_e('Shutter speed', 'chatbot'); ?></option>
                                    <option value="ISO"><?php esc_html_e('ISO', 'chatbot'); ?></option>
                                    <option value="White balance"><?php esc_html_e('White balance', 'chatbot'); ?></option>
                                    <option value="Exposure compensation"><?php esc_html_e('Exposure compensation', 'chatbot'); ?></option>
                                    <option value="Focus mode"><?php esc_html_e('Focus mode', 'chatbot'); ?></option>
                                    <option value="Metering mode"><?php esc_html_e('Metering mode', 'chatbot'); ?></option>
                                    <option value="Drive mode"><?php esc_html_e('Drive mode', 'chatbot'); ?></option>
                                    <option value="Image stabilization"><?php esc_html_e('Image stabilization', 'chatbot'); ?></option>
                                    <option value="Auto-Focus point"><?php esc_html_e('Auto-Focus point', 'chatbot'); ?></option>
                                    <option value="Flash mode"><?php esc_html_e('Flash mode', 'chatbot'); ?></option>
                                    <option value="Flash compensation"><?php esc_html_e('Flash compensation', 'chatbot'); ?></option>
                                    <option value="Picture style/picture control"><?php esc_html_e('Picture style/picture control', 'chatbot'); ?></option>
                                    <option value="Long exposure"><?php esc_html_e('Long exposure', 'chatbot'); ?></option>
                                    <option value="High-speed sync"><?php esc_html_e('High-speed sync', 'chatbot'); ?></option>
                                    <option value="Mirror lock-up"><?php esc_html_e('Mirror lock-up', 'chatbot'); ?></option>
                                    <option value="Bracketing"><?php esc_html_e('Bracketing', 'chatbot'); ?></option>
                                    <option value="Noise reduction"><?php esc_html_e('Noise reduction', 'chatbot'); ?></option>
                                    <option value="Image format"><?php esc_html_e('Image format', 'chatbot'); ?></option>
                                    <option value="Time-lapse"><?php esc_html_e('Time-lapse', 'chatbot'); ?></option>
                                    <option value="None"><?php esc_html_e('None', 'chatbot'); ?></option>
                                </select>
                            </div>
                            <div class="mb-5">
                                <label for="composition" class="qcld_seo-form-label"><?php esc_html_e('Composition:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="composition" id="composition">
                                    <option value="Rule of thirds" selected><?php esc_html_e('Rule of thirds', 'chatbot'); ?></option>
                                    <option value="Symmetry"><?php esc_html_e('Symmetry', 'chatbot'); ?></option>
                                    <option value="Leading lines"><?php esc_html_e('Leading lines', 'chatbot'); ?></option>
                                    <option value="Negative space"><?php esc_html_e('Negative space', 'chatbot'); ?></option>
                                    <option value="Frame within a frame"><?php esc_html_e('Frame within a frame', 'chatbot'); ?></option>
                                    <option value="Diagonal lines"><?php esc_html_e('Diagonal lines', 'chatbot'); ?></option>
                                    <option value="Triangles"><?php esc_html_e('Triangles', 'chatbot'); ?></option>
                                    <option value="S-curves"><?php esc_html_e('S-curves', 'chatbot'); ?></option>
                                    <option value="Golden ratio"><?php esc_html_e('Golden ratio', 'chatbot'); ?></option>
                                    <option value="Radial balance"><?php esc_html_e('Radial balance', 'chatbot'); ?></option>
                                    <option value="Contrast"><?php esc_html_e('Contrast', 'chatbot'); ?></option>
                                    <option value="Repetition"><?php esc_html_e('Repetition', 'chatbot'); ?></option>
                                    <option value="Simplicity"><?php esc_html_e('Simplicity', 'chatbot'); ?></option>
                                    <option value="Viewpoint"><?php esc_html_e('Viewpoint', 'chatbot'); ?></option>
                                    <option value="Foreground, middle ground, background"><?php esc_html_e('Foreground, middle ground, background', 'chatbot'); ?></option>
                                    <option value="Patterns"><?php esc_html_e('Patterns', 'chatbot'); ?></option>
                                    <option value="Texture"><?php esc_html_e('Texture', 'chatbot'); ?></option>
                                    <option value="Balance"><?php esc_html_e('Balance', 'chatbot'); ?></option>
                                    <option value="Color theory"><?php esc_html_e('Color theory', 'chatbot'); ?></option>
                                    <option value="Proportion"><?php esc_html_e('Proportion', 'chatbot'); ?></option>
                                    <option value="None"><?php esc_html_e('None', 'chatbot'); ?></option>
                                </select>
                            </div>
                            <div class="mb-5">
                                <label for="resolution" class="qcld_seo-form-label"><?php esc_html_e('Resolution:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="resolution" id="resolution">
                                    <option value="4K (3840x2160)" selected><?php esc_html_e('4K (3840x2160)', 'chatbot'); ?></option>
                                    <option value="1080p (1920x1080)"><?php esc_html_e('1080p (1920x1080)', 'chatbot'); ?></option>
                                    <option value="720p (1280x720)"><?php esc_html_e('720p (1280x720)', 'chatbot'); ?></option>
                                    <option value="480p (854x480)"><?php esc_html_e('480p (854x480)', 'chatbot'); ?></option>
                                    <option value="2K (2560x1440)"><?php esc_html_e('2K (2560x1440)', 'chatbot'); ?></option>
                                    <option value="1080i (1920x1080)"><?php esc_html_e('1080i (1920x1080)', 'chatbot'); ?></option>
                                    <option value="720i (1280x720)"><?php esc_html_e('720i (1280x720)', 'chatbot'); ?></option>
                                    <option value="None"><?php esc_html_e('None', 'chatbot'); ?></option>
                                </select>
                            </div>
                            <div class="mb-5">
                                <label for="color" class="qcld_seo-form-label"><?php esc_html_e('Color:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="color" id="color">
                                    <option value="RGB" selected><?php esc_html_e('RGB', 'chatbot'); ?></option>
                                    <option value="CMYK"><?php esc_html_e('CMYK', 'chatbot'); ?></option>
                                    <option value="Grayscale"><?php esc_html_e('Grayscale', 'chatbot'); ?></option>
                                    <option value="HEX"><?php esc_html_e('HEX', 'chatbot'); ?></option>
                                    <option value="Pantone"><?php esc_html_e('Pantone', 'chatbot'); ?></option>
                                    <option value="CMY"><?php esc_html_e('CMY', 'chatbot'); ?></option>
                                    <option value="HSL"><?php esc_html_e('HSL', 'chatbot'); ?></option>
                                    <option value="HSV"><?php esc_html_e('HSV', 'chatbot'); ?></option>
                                    <option value="LAB"><?php esc_html_e('LAB', 'chatbot'); ?></option>
                                    <option value="LCH"><?php esc_html_e('LCH', 'chatbot'); ?></option>
                                    <option value="LUV"><?php esc_html_e('LUV', 'chatbot'); ?></option>
                                    <option value="XYZ"><?php esc_html_e('XYZ', 'chatbot'); ?></option>
                                    <option value="YUV"><?php esc_html_e('YUV', 'chatbot'); ?></option>
                                    <option value="YIQ"><?php esc_html_e('YIQ', 'chatbot'); ?></option>
                                    <option value="YCbCr"><?php esc_html_e('YCbCr', 'chatbot'); ?></option>
                                    <option value="YPbPr"><?php esc_html_e('YPbPr', 'chatbot'); ?></option>
                                    <option value="YDbDr"><?php esc_html_e('YDbDr', 'chatbot'); ?></option>
                                    <option value="YCoCg"><?php esc_html_e('YCoCg', 'chatbot'); ?></option>
                                    <option value="YCgCo"><?php esc_html_e('YCgCo', 'chatbot'); ?></option>
                                    <option value="YCC"><?php esc_html_e('YCC', 'chatbot'); ?></option>
                                    <option value="None"><?php esc_html_e('None', 'chatbot'); ?></option>
                                </select>
                            </div>
                            <div class="mb-5">
                                <label for="special_effects" class="qcld_seo-form-label"><?php esc_html_e('Special Effects:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="special_effects" id="special_effects">
                                    <option value="Cinemagraph" selected><?php esc_html_e('Cinemagraph', 'chatbot'); ?></option>
                                    <option value="Bokeh"><?php esc_html_e('Bokeh', 'chatbot'); ?></option>
                                    <option value="Panorama"><?php esc_html_e('Panorama', 'chatbot'); ?></option>
                                    <option value="HDR"><?php esc_html_e('HDR', 'chatbot'); ?></option>
                                    <option value="Long exposure"><?php esc_html_e('Long exposure', 'chatbot'); ?></option>
                                    <option value="Timelapse"><?php esc_html_e('Timelapse', 'chatbot'); ?></option>
                                    <option value="Slow motion"><?php esc_html_e('Slow motion', 'chatbot'); ?></option>
                                    <option value="Stop-motion"><?php esc_html_e('Stop-motion', 'chatbot'); ?></option>
                                    <option value="Tilt-shift"><?php esc_html_e('Tilt-shift', 'chatbot'); ?></option>
                                    <option value="Zoom blur"><?php esc_html_e('Zoom blur', 'chatbot'); ?></option>
                                    <option value="Motion blur"><?php esc_html_e('Motion blur', 'chatbot'); ?></option>
                                    <option value="Lens flare"><?php esc_html_e('Lens flare', 'chatbot'); ?></option>
                                    <option value="Sunburst"><?php esc_html_e('Sunburst', 'chatbot'); ?></option>
                                    <option value="Starburst"><?php esc_html_e('Starburst', 'chatbot'); ?></option>
                                    <option value="Double exposure"><?php esc_html_e('Double exposure', 'chatbot'); ?></option>
                                    <option value="Cross processing"><?php esc_html_e('Cross processing', 'chatbot'); ?></option>
                                    <option value="Fish-eye"><?php esc_html_e('Fish-eye', 'chatbot'); ?></option>
                                    <option value="Vignette"><?php esc_html_e('Vignette', 'chatbot'); ?></option>
                                    <option value="Infrared"><?php esc_html_e('Infrared', 'chatbot'); ?></option>
                                    <option value="3D"><?php esc_html_e('3D', 'chatbot'); ?></option>
                                    <option value="None"><?php esc_html_e('None', 'chatbot'); ?></option>
                                </select>
                            </div>
                            <div class="mb-5">
                                
                                <label for="img_size" class="qcld_seo-form-label"><?php esc_html_e('Size:', 'chatbot'); ?></label>
                                <select class="qcld_seo-input" name="img_size" id="img_size">
                                    <option value="256x256"><?php esc_html_e('256x256', 'chatbot'); ?></option>
                                    <option value="512x512" selected><?php esc_html_e('512x512', 'chatbot'); ?></option>
                                    <option value="1024x1024"><?php esc_html_e('1024x1024', 'chatbot'); ?></option>
                                </select>
                                
                            </div>
                            <div class="mb-5">
                                
                                <label for="num_images" class="qcld_seo-form-label"><?php esc_html_e('# of:', 'chatbot'); ?></label>
                                <select name="num_images" id="num_images" class="qcld_seo-input">
                                    <option value="1"><?php esc_html_e('1', 'chatbot'); ?></option>
                                    <option value="2"><?php esc_html_e('2', 'chatbot'); ?></option>
                                    <option value="3"><?php esc_html_e('3', 'chatbot'); ?></option>
                                    <option value="4" selected><?php esc_html_e('4', 'chatbot'); ?></option>
                                    <option value="5"><?php esc_html_e('5', 'chatbot'); ?></option>
                                    <option value="6"><?php esc_html_e('6', 'chatbot'); ?></option>
                                    <option value="7"><?php esc_html_e('7', 'chatbot'); ?></option>
                                    <option value="8"><?php esc_html_e('8', 'chatbot'); ?></option>
                                </select>
                             
                            </div>
                        </div>
                    </div>
              
                </div>
            </div>
            <script>
                jQuery(document).ready(function ($) {
                    $('.qcld_seo-collapse-title').click(function () {
                        if (!$(this).hasClass('qcld_seo-collapse-active')) {
                            $('.qcld_seo-collapse').removeClass('qcld_seo-collapse-active');
                            $('.qcld_seo-collapse-title span').html('+');
                            $(this).find('span').html('-');
                            $(this).parent().addClass('qcld_seo-collapse-active');
                        }
                    })
                })
            </script>
           </form>
        </div>
    </div>
</div>

<?php
 } else { ?>
<div class="row my-4">
    <div  class="col-md-12">
        <?php esc_html_e('Fine tuning and training is available with the ', 'chatbot');?>
        <a href="https://www.wpbot.pro/pricing/"><?php esc_html_e('WPBot Pro Professional', 'chatbot'); ?></a>
        <?php esc_html_e(' and ', 'chatbot'); ?>
        <a href="https://www.wpbot.pro/pricing/"><?php esc_html_e('Master', 'chatbot'); ?></a>
        <?php esc_html_e(' Licenses', 'chatbot'); ?>
    </div>
</div>
<?php } ?>