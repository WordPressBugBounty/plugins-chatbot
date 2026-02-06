<?php 
global $wpchatbot_pro_professional_init,$wpchatbot_pro_master_init;
if((isset($wpchatbot_pro_master_init) && $wpchatbot_pro_master_init->is_valid()) || (isset($wpchatbot_pro_professional_init) && $wpchatbot_pro_professional_init->is_valid()) || (function_exists('get_openaiaddon_valid_license') && get_openaiaddon_valid_license())){
?>
<div class="row">
    <div  class="col-md-12">
       
        <form class="file_form">
          
            <input type="file" (change)="fileEvent($event)" class="inputfile" id="openfileinput" style="display:none"/>
          
        </form>
       <form class="file_form_gpt">
            <div class="success-message alert alert-info"></div>
            <div class="error-message alert alert-danger"></div>
            <input type="file" (change)="fileEvent($event)" class="inputfile" id="openfileinput_gpt" style="display:none"/>
            <label for="openfileinput_gpt" class="huge ui grey button">
                <span class="dashicons dashicons-cloud-upload"></span>
                <?php esc_html_e('Upload JSONL GPT 3.5','chatbot'); ?>
            </label>
        </form>  
        </br>
        <a href="https://wpbot.pro/myfile.jsonl" download><?php esc_html_e( 'Right click and Save the Example jsonl file','chatbot');?></a>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead><tr><td> <?php esc_html_e( 'File name','chatbot'); ?></td><td><?php esc_html_e( 'File id','chatbot');?></td><td><?php esc_html_e( 'Action','chatbot');?></td></tr></thead>
                <tbody id="openaiFileList">
                    
                </tbody>
            </table>
        </div>
        <div class="my-5">
            <h2><?php esc_html_e( 'GPT 3.5 Fine Tuned Models List','chatbot');?></br></h2>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead><tr><td><?php esc_html_e( 'FT Model','chatbot');?></td><td><?php esc_html_e( 'Status','chatbot');?> </td><td><?php esc_html_e( 'File Name','chatbot');?></td><td> <?php esc_html_e( 'File Id','chatbot');?></td></tr></thead>
                <tbody id="openaiFTListGPT">
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
 } else { ?>
<div class="row my-4">
    <div  class="col-md-12">
    <div  class="col-md-12">
        <?php esc_html_e('Fine tuning and training is available with the ','chatbot');?>
        <a href="https://www.wpbot.pro/pricing/"><?php esc_html_e('WPBot Pro Professional','chatbot'); ?></a>
        <?php esc_html_e(' and ','chatbot'); ?>
        <a href="https://www.wpbot.pro/pricing/"><?php esc_html_e('Master','chatbot'); ?></a>
        <?php esc_html_e(' Licenses','chatbot'); ?>
    </div>
    </div>
</div>
<?php } ?>

<div id="qcld-ft-modal" class="modal fade bd-example-modal-sm" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle"> <?php esc_html_e('Create your Fine Tune','chatbot'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="qcld_openai_suffix" class="form-label"><?php esc_html_e( 'Suffix for custom model','chatbot');?></label>
                        <input id="qcld_openai_ft_suffix" class="form-control" type="text" name="qcld_openai_ft_suffix" value="<?php echo esc_attr( get_option( 'qcld_openai_suffix') ); ?>">
                        <input id="qcld_openai_ft_fileid" class="form-control" type="hidden" name="qcld_openai_ft_fileid" value="">
                    </div>
                    <div class="form-group">
                        <label for="exampleFormControlSelect1">Fine tune model</label>
                        <select class="form-select" aria-label="Default select example" name="qcld_openai_ft_engines" id="qcld_openai_ft_engines">
                            <option <?php echo ((get_option( 'openai_engines') == '') ? 'selected' : '') ; ?>><?php esc_html_e( 'Please select Engines','chatbot');?></option>
                            <option value="gpt-3.5-turbo-1106" <?php echo ((get_option( 'openai_engines') == 'gpt-3.5-turbo-1106') ? 'selected' : '') ; ?>><?php esc_html_e( 'gpt-3.5-turbo-1106 (recommended)','chatbot');?></option>
                            <option value="gpt-3.5-turbo-0613" <?php echo ((get_option( 'openai_engines') == 'gpt-3.5-turbo-0613') ? 'selected' : '') ; ?>><?php esc_html_e( 'gpt-3.5-turbo-0613','chatbot');?></option>
                            <option value="gpt-4-0613" <?php echo ((get_option( 'openai_engines') == 'gpt-4-0613') ? 'selected' : '') ; ?>><?php esc_html_e( 'gpt-4-0613 (experimental)','chatbot');?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"> <?php esc_html_e('Close','chatbot'); ?></button>
                        <button type="button" class="btn btn-primary create_ft_model"> <?php esc_html_e('Create Fine tune','chatbot'); ?></button>
                </div>
            </div>
         </div>
    </div>

