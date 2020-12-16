<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <button type="submit" form="form-module" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary">
                    <i class="fa fa-save"></i>
                </button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default">
                    <i class="fa fa-reply"></i>
                </a>
            </div>
            <h1><?php echo $heading_title; ?></h1>
            <ul class="breadcrumb">
               <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                <li>
                    <a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
                </li>
                <?php } ?>
            </ul>
        </div>
    </div>
    <div class="container-fluid">
        <?php if ($error_warning) { ?>
        <div class="alert alert-danger alert-dismissible">
            <i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
         <?php if ($token_error) { ?>
        <div class="alert alert-danger alert-dismissible">
            <i class="fa fa-exclamation-circle"></i> <?php echo $token_error; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
       <?php } ?>
       <?php if (!empty($cronsync_warning)) { ?>
        <div class="alert alert-warning alert-dismissible">
            <i class="fa fa-exclamation-circle"></i> <?php echo $text_cron_sync_error; ?> <a href="https://github.com/Knawat/knawat-dropshipping-opencart/wiki/3.-Cron-Job-Integration" id="install_guide" target="_blank"> <?php echo $cronjob; ?> </a> <?php echo $trigger; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
        <?php if (!empty($ordersync_warning)) { ?>
        <div class="alert alert-warning alert-dismissible knawat_message">
            <i class="fa fa-exclamation-circle"></i>
             <?php echo $warning_ordersync;  ?><a href="#" id="start_ordersync"><?php echo $text_ordersync_botton; ?></a>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
        <?php if ($success) { ?>
        <div class="alert alert-success alert-dismissible">
            <i class="fa fa-check-circle"></i> <?php echo  $success; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
            </div>
            <div class="panel-body">
                <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-module" class="form-horizontal">

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
                        <div class="col-sm-10">
                            <select name="module_knawat_dropshipping_status" id="input-status" class="form-control">
                                <?php if ($module_knawat_dropshipping_status) { ?>
                                <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                                <option value="0"><?php echo  $text_disabled; ?></option>
                                <?php } else{ ?>
                                <option value="1"><?php echo $text_enabled; ?></option>
                                <option value="0" selected="selected"><?php echo  $text_disabled; ?></option>
                                <?php } ?>
                            </select>
                            <?php if($error_status){ ?>
                            <div class="text-danger"><?php echo $error_status; ?></div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-knawat-consumer-key"><?php echo $entry_consumer_key; ?></label>
                        <div class="col-sm-10">
                            <input type="text" name="module_knawat_dropshipping_consumer_key" value="<?php echo $module_knawat_dropshipping_consumer_key; ?>" placeholder="<?php echo $consumer_key_placeholder; ?>"
                                class="form-control" id="input-knawat-consumer-key" />
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-knawat-consumer-secret"><?php echo $entry_consumer_secret; ?></label>
                        <div class="col-sm-10">
                            <input type="text" name="module_knawat_dropshipping_consumer_secret" value="<?php echo  $module_knawat_dropshipping_consumer_secret ?>"
                                placeholder="<?php echo $consumer_secret_placeholder; ?>" class="form-control" id="input-knawat-consumer-secret"
                            />
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label"><?php echo $entry_connection; ?></label>
                        <div class="col-sm-10">
                            <?php if($token_valid) { ?>
                            <h4><span class="label label-success"><?php echo $text_connected; ?></span></h4>
                            <small><?php echo $text_connected_desc; ?></small>
                            <?php } else { ?>
                            <h4><span class="label label-danger"><?php echo $text_notconnected; ?></span></h4>
                            <small><?php echo $text_notconnected_desc; ?></small>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-2 control-label"><?php echo $entry_store; ?></label>
                        <div class="col-sm-10">
                            <div class="well well-sm" style="height: 150px; overflow: auto;">
                                <?php foreach ($stores as $store) { ?>
                                <div class="checkbox">
                                    <label>
                                    <?php if(in_array($store['store_id'], $module_knawat_dropshipping_store)) { ?>
                                        <input type="checkbox" name="module_knawat_dropshipping_store[]" value="<?php echo $store['store_id']; ?>" checked="checked" /> <?php echo  $store['name']; ?> <?php } else { ?>
                                        <input type="checkbox" name="module_knawat_dropshipping_store[]" value="<?php echo $store['store_id']; ?>" /> <?php echo  $store['name']; ?> <?php } ?> </label>
                                </div>
                                <?php } ?>
                                </div>
                        </div>
                    </div>
                    <?php if($token_valid){ ?>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-knawat-consumer-secret"><?php echo $text_import_products; ?></label>
                        <div class="col-sm-10 ajax_import">
                            <button id="run_import" class="btn btn-primary"><?php echo $text_run_import; ?></button>
                            <div class="import_inprocess" style="display: none;">
                                <img src="<?php echo $knawat_ajax_loader; ?>" style="width:30px;" />
                                <strong style="margin-left: 10px;"><?php echo $text_import_inprogress; ?></strong>
                                <div class="import_status" style="margin-top: 10px;">
                                    <p><?php echo $text_import_stats; ?>:</p>
                                    <strong><?php echo $text_imported; ?>:</strong> 0 <?php echo $text_products; ?>
                                    <br>
                                    <strong><?php echo $text_updated; ?>:</strong> 0 <?php echo $text_products; ?>
                                    <br>
                                    <strong><?php echo $text_failed; ?>:</strong> 0 <?php echo $text_products; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="last-update-knawat"><?php echo $text_sync; ?></label>
                        <div class="col-sm-10">
                            <meter id="last-update-knawat" value="<?php echo $products_count-$products_synced; ?>" min="0" max="<?php echo $products_count; ?>" style="height: 35px; width: 815px;"></meter>
                            <h4><small><?php echo $text_sync_product1 . ' ' . $products_count . ' ' . $text_sync_product2 . ' ' . $products_count-$products_synced . ' ' . $text_sync_product3; ?></small></h4>
                            <a id="knawat-resetsync" class="btn btn-primary"><?php echo $text_reset_sync; ?></a>
                        </div>
                    </div>
                    <?php } ?>
                    <div class="form-group">
                        <label class="col-sm-2 control-label"><?php echo $text_import_note; ?></label>
                        <div class="col-sm-10">
                            <h4><small><?php echo $cron_url_info; ?></small></h4>
                            <b><?php echo $cron_url; ?></b>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php echo $footer; ?>

    <script type="text/javascript">
        var knawat_ajax_url = '<?php echo $knawat_ajax_url; ?>';
        $(document).ready(function () {
            $('#run_import').on('click', function (e) {
                e.preventDefault();
                process_step();
                knawat_ajax_import_start();
            });
            $("#knawat-resetsync").on('click',function (e) {
                var x = window.location.href + '&reset-sync=true';
                document.getElementById('knawat-resetsync').setAttribute('href', x);
            });
        });

        function process_step(data) {
            $.ajax({
                type: 'POST',
                url: knawat_ajax_url.replace(/&amp;/g, '&'),
                data: {
                    process_data: data
                },
                dataType: "json",
                success: function (response) {
                    if (response.is_complete) {
                        /* Done Operation here. */
                        knawat_ajax_import_stop();
                        jQuery(".page-header .container-fluid").append(
                            '<div class="knawat_import_message alert alert-success"><i class="fa fa-info-circle"></i> <?php echo $success_ajaximport; ?></div>'
                        );
                        jQuery('.import_inprocess .import_status').html('');

                    } else {
                        /*Update import stats.*/
                        jQuery('.import_inprocess .import_status').html(
                            '<p><?php echo $text_import_stats; ?>:</p><strong><?php echo $text_imported; ?>:</strong> ' +
                            response.imported +
                            ' <?php echo $text_products; ?><br><strong><?php echo $text_updated; ?>:</strong> ' + response.updated +
                            ' <?php echo $text_products; ?><br><strong><?php echo $text_failed; ?>:</strong> ' + response.failed +
                            ' <?php echo $text_products; ?>')
                        /*Run next batch.*/
                        process_step(response);
                    }

                }
            }).fail(function (response) {
                if (window.console && window.console.log) {
                    console.log(response);
                }
                knawat_ajax_import_stop();
                jQuery(".page-header .container-fluid").append(
                    '<div class="knawat_import_message alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_ajaximport; ?></div>'
                );
            });
        }

        function knawat_ajax_import_start() {
            jQuery(".page-header .container-fluid .knawat_import_message").remove();
            jQuery(".page-header .container-fluid").append(
                '<div class="knawat_import_message alert alert-warning"><i class="fa fa-info-circle"></i> <?php echo $warning_ajaximport; ?></div>'
            );
            jQuery('#run_import').hide();
            jQuery('.ajax_import .import_inprocess').show();
        }

        function knawat_ajax_import_stop() {
            jQuery(".page-header .container-fluid .knawat_import_message").remove();
            jQuery('#run_import').show();
            jQuery('.ajax_import .import_inprocess').hide();
        }

        var knawat_ordersync_url = '<?php echo $knawat_ordersync_url; ?>';
        $(document).ready(function () {
            $.ajaxSetup({
                headers : {
                    'CsrfToken': '<?php echo $csrf_token; ?>'
                }
            });
            $('#start_ordersync').on('click', function (e) {
                $('#start_ordersync').html('<?php echo $text_syncing; ?>');
                e.preventDefault();
                $.ajax({
                    type: 'POST',
                    url: knawat_ordersync_url.replace(/&amp;/g, '&'),
                    dataType: "json",
                    success: function (response) {
                        if ( 'success' === response.status) {
                            jQuery(".knawat_message").remove();
                            jQuery(".page-header .container-fluid").append(
                                '<div class="knawat_message alert alert-success"><i class="fa fa-info-circle"></i> <?php echo $success_ordersync; ?></div>'
                            );
                        }else{
                            jQuery(".knawat_message").remove();
                            if (typeof response.error !== 'undefined') {
                                var order_error = response.error;
                                if( '1' === order_error ){
                                    order_error = '<?php echo $error_wrong; ?>';
                                }
                                jQuery(".page-header .container-fluid").append( '<div class="knawat_message alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' + order_error + '</div>' );
                            }
                        }
                    }
                }).fail(function (response) {
                    if (window.console && window.console.log) {
                        console.log(response);
                    }
                    jQuery(".knawat_message").remove();
                    jQuery(".page-header .container-fluid").append(
                        '<div class="knawat_message alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_wrong; ?></div>'
                    );
                });

            });
             window.onbeforeunload = function(){
                    if($(".import_inprocess:visible").length){
                            return false;
                    }
                };
        });
    </script>