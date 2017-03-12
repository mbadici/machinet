<!DOCTYPE html>
<html>
	<head>
		<title><?php echo isset($_['application']) && !empty($_['application'])?$_['application'].' | ':'' ?>ownCloud <?php echo OC_User::getUser()?' ('.OC_User::getUser().') ':'' ?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<link rel="shortcut icon" href="<?php echo image_path('', 'favicon.png'); ?>" /><link rel="apple-touch-icon-precomposed" href="<?php echo image_path('', 'favicon-touch.png'); ?>" />
		<?php foreach($_['cssfiles'] as $cssfile): ?>
			<link rel="stylesheet" href="<?php echo $cssfile; ?>" type="text/css" media="screen" />
		<?php endforeach; ?>
		<script type="text/javascript">
			var oc_webroot = '<?php echo OC::$WEBROOT; ?>';
			var oc_appswebroots = <?php echo $_['apps_paths'] ?>;
			var oc_current_user = '<?php echo OC_User::getUser() ?>';
			var oc_requesttoken = '<?php echo $_['requesttoken']; ?>';
			var oc_requestlifespan = '<?php echo $_['requestlifespan']; ?>';
		</script>
		<?php foreach($_['jsfiles'] as $jsfile): ?>
			<script type="text/javascript" src="<?php echo $jsfile; ?>"></script>
		<?php endforeach; ?>
		<script type="text/javascript" src="<?php echo OC::$WEBROOT.'/themes/kolab/core/js/kolab.js'; ?>"></script>
		<?php foreach($_['headers'] as $header): ?>
			<?php
				echo '<'.$header['tag'].' ';
				foreach($header['attributes'] as $name=>$value) {
					echo "$name='$value' ";
				};
				echo '/>';
			?>
		<?php endforeach; ?>
	</head>

	<body id="<?php echo $_['bodyid'];?>">
		<nav><div id="navigation">
			<ul id="apps" class="svg">
				<?php foreach($_['navigation'] as $entry): ?>
					<li data-id="<?php echo $entry['id']; ?>"><a style="background-image:url(<?php echo $entry['icon']; ?>)" href="<?php echo $entry['href']; ?>" title="" <?php if( $entry['active'] ): ?> class="active"<?php endif; ?>><?php echo $entry['name']; ?></a>
					</li>
				<?php endforeach; ?>
			</ul>

			<ul id="settings" class="svg">
				<img role=button tabindex=0 id="expand" class="svg" alt="<?php echo $l->t('Settings');?>" src="<?php echo image_path('', 'actions/settings.svg'); ?>" />
				<span><?php echo $l->t('Settings');?></span>
				<div id="expanddiv" <?php if($_['bodyid'] == 'body-user') echo 'style="display:none;"'; ?>>
				<?php foreach($_['settingsnavigation'] as $entry):?>
					<li><a style="background-image:url(<?php echo $entry['icon']; ?>)" href="<?php echo $entry['href']; ?>" title="" <?php if( $entry["active"] ): ?> class="active"<?php endif; ?>><?php echo $entry['name'] ?></a></li>
				<?php endforeach; ?>
				</div>
			</ul>
		</div></nav>

		<div id="content">
			<?php echo $_['content']; ?>
		</div>
	</body>
</html>
