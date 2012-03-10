<?php
?>
      <div id="alert_region_view">
        <div class="report_row row">
          <?php 
              print "<div id='geometries' style='width:50%;float:left;'>".
                form::dropdown('sectors', $form['sectors'])
                .'</div>';
          ?>
        </div>
				<div class="report_row row">
					<div id="regionDivMap" class="report_map map_holder_reports"></div>
        </div>
      </div>
