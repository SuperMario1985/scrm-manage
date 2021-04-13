
<?php

use yii\helpers\Html;
use yii\widgets\LinkPager;

$this->title = '基础配置';
?>
<?= Html::cssFile('@web/css/dataTable.css') ?>
<?= Html::cssFile('@web/plugins/dataTables/dataTables.bootstrap.css') ?>
<style>
    .sym-public1 {
        width: 50%;
    }

    .sym-display {
        display: none !important;
    }

    #home span {
        line-height: 2.8rem;
        font-size: 14px;
    }

    .sym-is-setting {
        background: white !important;
        border: none !important;
    }
    .yulan {
        cursor: pointer;
        color: rgb(36,137,257);
    }
</style>
<div class="row wrapper border-bottom white-bg page-heading">
    <div class="col-lg-10">
        <h2>管理后台</h2>
        <ol class="breadcrumb">
            <li>
                <a>系统</a>
            </li>
            <li>
                <a>系统设置</a>
            </li>
            <li class="active">
                <strong>基础配置</strong>
            </li>
        </ol>
    </div>
    <div class="col-lg-2"></div>
</div>
<div class="wrapper wrapper-content">
    <div class="row">
        <div>
            <div class="ibox-content">
                <div class="form-group">
                    <div class="row">
                            <div role="tabpanel" class="tab-pane active" id="home">
                                <div id="saveLoad">
                                    <form class="form-horizontal" id="form" method="post" enctype="multipart/form-data">
                                            <div role="tabpanel" class="tab-pane" id="web-app">
                                                <div class="form-group" id="localUp">
                                                    <label for="inputEmail3"
                                                           class="col-sm-2 control-label">底部版权设置 <span
                                                                style="color: #ff0000;line-height: ">*</span></label>
                                                    <div class="col-sm-10">
                                                        <button style="" type="button" class="btn btn-primary"
                                                                onclick="changeUpdalodClick(1)">上传图片
                                                        </button>
                                                        <br>
                                                        <input type="file"
                                                               style="display: none"
                                                               id="techImg"
                                                               accept="image/*"
                                                               onchange="LogUpload(this.value)">
                                                        <br>

                                                        <img src="<?php if ($data['web_tech_img'] )
                                                            echo $data['web_tech_img']; ?>"
                                                             id="web_tech_img"
                                                             class="img-rounded"
                                                             width="320"
                                                             height="30"
                                                            <?php if(!$data['web_tech_img']) echo 'style="display:none"';?>">

                                                        <br><br>
                                                        <p class="help-block">
                                                            用于平台h5页面底部技术支持展示，建议尺寸：640*60像素的.png格式图片&nbsp;&nbsp;&nbsp;
                                                            <span>为了更好的体验，图片大小不超过20KB</span>
                                                            <a href="javascript:void(0);"  id="yulan">效果预览</a>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div style="float: left;margin-left: 17%;">

                                                    <button
                                                            onclick="addDataConfig(1)"
                                                            type="button" class="btn btn-success">提交
                                                    </button>

                                                </div>
                                            </div>
                                    </form>
                                </div>
                            </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

</div>


<div class="modal fade inmodal" tabindex="-1" id="actionModal" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-body">
                <div style="width: 375px;height: 667px;text-align: center;margin: 0 auto">

                    <img src="/images/mobile_bg.jpg" alt="" style="width: 100%;height: 100%" >
                    <img src="" id="tech_img"
                         style="width: 213px;height: 20px; position: relative;top: -45px">

                </div>
            </div>
        </div>
    </div>
</div>
<script>


    var saveload;


    function changeUpdalodClick (val) {
        switch (val) {
            case 1: //底部版权设置
                $("#techImg").click()
                break;
        }

    }

    $(function(){
        $("#yulan").on("click",function(){

            var src = $("#web_tech_img").attr('src')

            if (src == "") {
                $("#tech_img").hide()
            }else{
                $("#tech_img").attr('src', src)
                $("#tech_img").show()
            }


            $("#actionModal").modal('show');
        })
    })

    function checkSize(file) {
        return file.files[0].size <= 20000;
    }


    function LogUpload (e) {
        console.log($('#techImg')[0].files[0]);
        if (!checkSize($('#techImg')[0])) {
            swal("错误", "上传图片大小不能超过20KB", "error");
            return false;
        }
        var windowURL = window.URL || window.webkitURL;
        var dataURL = windowURL.createObjectURL($('#techImg')[0].files[0]);
        $('#web_tech_img').attr('src', dataURL)
        $("#web_tech_img").show()

    }

    function addDataConfig (local) {
        var formData = new FormData();

        var url = '/admin/admin-config/add-config'

        if (typeof($("#techImg")[0].files[0]) != "undefined" && checkSize($("#techImg")[0])) {
            formData.append("techImg", $("#techImg")[0].files[0]);
        }


        $.ajax({
            url        : url,
            type       : 'POST',
            cache      : false,
            data       : formData,
            processData: false,
            contentType: false,
            success    : function (result) {
                result = JSON.parse(result);
                if (result.error == 1) {
                    swal(result.msg, result.msg, "error");
                } else {
                    swal("完成", "操作已完完成", "success");
                    setTimeout(function () {
                        window.location.reload()
                    }, 1000)
                }
            }
        });
    }

</script>
