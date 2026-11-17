<?php
require(__DIR__ . '/../../config_admin.php');

if(!canManageAddons()){
	die();
}
?>
<?php
require_once(BOOM_PATH . '/system/addon_page_appearance.php');
addonPageAppearanceEnsureSchema();
$apa = addonPageAppearanceGet();
$apaFile = basename((string)$apa['bg_file']);
$apaPreview = ($apaFile !== '' && preg_match('/^apg_[a-f0-9]{32}\.(jpg|png|gif|webp)$/i', $apaFile)) ? 'upload/addon_page_appearance/' . $apaFile : '';
$apaHex = ltrim($apa['tint_color'], '#');
$apaRgb = array(hexdec(substr($apaHex,0,2)), hexdec(substr($apaHex,2,2)), hexdec(substr($apaHex,4,2)));
$apaPreviewStyle = $apaPreview !== '' ? 'background-image:linear-gradient(rgba('.$apaRgb[0].','.$apaRgb[1].','.$apaRgb[2].','.((int)$apa['tint_opacity']/100).'),rgba('.$apaRgb[0].','.$apaRgb[1].','.$apaRgb[2].','.((int)$apa['tint_opacity']/100).')),url('.htmlspecialchars($apaPreview, ENT_QUOTES, 'UTF-8').');background-size:'.htmlspecialchars($apa['bg_size'], ENT_QUOTES, 'UTF-8').';' : 'background-color:#242424;';
?>
<?php echo elementTitle($lang['manage_addons']); ?>
<div class="page_full">
	<div class="page_element">
        <div class="config_section apa-config-section">
            <h3 class="label"><i class="fa fa-panorama"></i> مظهر صفحات الإضافات</h3>
            <p class="sub_text">يُطبّق هذا المظهر على صفحات الإضافات الرئيسية التي تمتد بعرض مساحة الشات فقط، وتفتح أسفل الشريط العلوي وفوق الشريط السفلي. النوافذ الصغيرة والمعاينات المنبثقة تبقى بأحجامها الأصلية. اختر صورة من معرض الهاتف أو ملفات الكمبيوتر، ثم حدّد لون التظليل ودرجته.</p>
            <div id="apa_preview" class="apa-preview" data-image="<?php echo htmlspecialchars($apaPreview, ENT_QUOTES, 'UTF-8'); ?>" style="<?php echo $apaPreviewStyle; ?>">
                <div class="apa-preview-caption">معاينة خلفية صفحات الإضافات الرئيسية</div>
            </div>
            <div class="apa-fields">
                <div class="setting_element apa-file-field">
                    <label class="label" for="apa_background_file">اختيار خلفية من المعرض</label>
                    <input id="apa_background_file" class="full_input" type="file" accept="image/jpeg,image/png,image/gif,image/webp" />
                    <p class="sub_text">الصيغ المدعومة: JPG وPNG وGIF وWEBP. الحد الأقصى 5 ميجابايت. في الهاتف سيفتح منتقي الصور/الملفات.</p>
                </div>
                <div class="setting_element">
                    <label class="label" for="apa_tint_color">لون التظليل</label>
                    <input id="apa_tint_color" type="color" value="<?php echo htmlspecialchars($apa['tint_color'], ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="setting_element apa-opacity-field">
                    <label class="label" for="apa_tint_opacity">قوة التلوين: <span id="apa_opacity_value"><?php echo (int)$apa['tint_opacity']; ?>%</span></label>
                    <input id="apa_tint_opacity" type="range" min="0" max="90" step="1" value="<?php echo (int)$apa['tint_opacity']; ?>" />
                </div>
                <div class="setting_element">
                    <label class="label" for="apa_bg_size">طريقة عرض الصورة</label>
                    <select id="apa_bg_size" class="full_input">
                        <option value="cover" <?php echo $apa['bg_size']==='cover'?'selected':''; ?>>ملء مساحة الصفحة مع الحفاظ على التناسب</option>
                        <option value="contain" <?php echo $apa['bg_size']==='contain'?'selected':''; ?>>إظهار الصورة كاملة</option>
                        <option value="100% 100%" <?php echo $apa['bg_size']==='100% 100%'?'selected':''; ?>>تمديد الصورة لتغطية المساحة</option>
                    </select>
                </div>
            </div>
            <div class="apa-actions">
                <button type="button" class="reg_button theme_btn" onclick="apaSaveAppearance();"><i class="fa fa-save"></i> حفظ خلفية الصفحات الرئيسية والتلوين</button>
                <button type="button" class="reg_button default_btn" onclick="apaRemoveBackground();"><i class="fa fa-eraser"></i> إزالة الخلفية</button>
            </div>
        </div>
        <style>
        .apa-config-section{margin-bottom:22px}.apa-preview{width:100%;height:180px;border:1px solid rgba(127,127,127,.35);border-radius:12px;background-position:center;background-repeat:no-repeat;background-color:#242424;display:flex;align-items:flex-end;overflow:hidden;margin:12px 0 18px;background-size:cover}.apa-preview-caption{width:100%;padding:12px;text-align:center;color:#fff;font-weight:700;text-shadow:0 1px 3px #000;background:rgba(0,0,0,.28)}.apa-fields{display:grid;grid-template-columns:minmax(0,1fr) minmax(130px,.35fr);gap:12px;align-items:end}.apa-fields .setting_element{min-width:0}.apa-fields input[type=color]{width:64px;height:42px;border:0;background:transparent;padding:0}.apa-fields input[type=range]{width:100%}.apa-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.apa-actions button{flex:1;min-width:170px}@media(max-width:600px){.apa-fields{grid-template-columns:1fr}.apa-preview{height:145px}}
        </style>
        <script data-cfasync="false">
        (function(){
            function apaGetRgb(hex){hex=(hex||'#000000').replace('#','');return [parseInt(hex.substr(0,2),16)||0,parseInt(hex.substr(2,2),16)||0,parseInt(hex.substr(4,2),16)||0];}
            var apaPreviewUrl='';
            function apaPreview(){var c=document.getElementById('apa_tint_color').value;var a=(parseInt(document.getElementById('apa_tint_opacity').value,10)||0)/100;var rgb=apaGetRgb(c);var p=document.getElementById('apa_preview');var image=p.dataset.image||'';p.style.backgroundImage='linear-gradient(rgba('+rgb.join(',')+','+a+'),rgba('+rgb.join(',')+','+a+'))'+(image?',url("'+image+'")':'');p.style.backgroundSize=document.getElementById('apa_bg_size').value;document.getElementById('apa_opacity_value').textContent=Math.round(a*100)+'%';}
            function apaApplyLiveCss(r){var old=document.getElementById('addon-page-appearance-css');if(old)old.remove();if(r&&r.css){var holder=document.createElement('div');holder.innerHTML=r.css;var style=holder.querySelector('#addon-page-appearance-css');if(style)document.head.appendChild(style);}}
            window.apaSaveAppearance=function(){var d=new FormData();d.append('token',utk);d.append('action','save');d.append('tint_color',document.getElementById('apa_tint_color').value);d.append('tint_opacity',document.getElementById('apa_tint_opacity').value);d.append('bg_size',document.getElementById('apa_bg_size').value);var f=document.getElementById('apa_background_file').files[0];if(f)d.append('background',f);$.ajax({url:'system/action/addon_page_appearance.php',type:'POST',data:d,processData:false,contentType:false,dataType:'json',success:function(r){if(r&&r.code===1){apaApplyLiveCss(r);callSuccess(r.message||'تم حفظ مظهر صفحات الإضافات');loadLob('admin/setting_addons.php');}else{callError((r&&r.message)||system.error);}},error:function(){callError(system.error);}});};
            window.apaRemoveBackground=function(){if(!confirm('هل تريد إزالة خلفية صفحات الإضافات؟'))return;$.post('system/action/addon_page_appearance.php',{token:utk,action:'clear'},function(r){if(r&&r.code===1){apaApplyLiveCss(r);callSuccess('تمت إزالة الخلفية');loadLob('admin/setting_addons.php');}else{callError((r&&r.message)||system.error);}},'json').fail(function(){callError(system.error);});};
            $(function(){document.getElementById('apa_tint_color').addEventListener('input',apaPreview);document.getElementById('apa_tint_opacity').addEventListener('input',apaPreview);document.getElementById('apa_bg_size').addEventListener('change',apaPreview);document.getElementById('apa_background_file').addEventListener('change',function(){var f=this.files&&this.files[0];if(!f)return;if(apaPreviewUrl){URL.revokeObjectURL(apaPreviewUrl);}apaPreviewUrl=URL.createObjectURL(f);document.getElementById('apa_preview').dataset.image=apaPreviewUrl;apaPreview();});});
        })();
        </script>
		<div id="addons_list">
		<?php echo adminAddonsList(); ?>
		</div>
	</div>
</div>