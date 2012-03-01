<?php include( App::$conf['dir']['views'] . "/header.php" ); ?>

<form class="form-horizontal" action="" method="post">
	<legend>Login</legend>
    
	<fieldset>
		<?php App::the_errors($result); ?>
		
        <div class="control-group">
        	<label class="control-label">Username:</label> 
            <div class="controls">
            	<input type="text" name="username"/>
            </div>
        </div>
		
        <div class="control-group">
        	<label class="control-label">Password: </label>
            <div class="controls">
            	<input type="password" name="password"/>
            </div>
        </div>
        
        <div class="control-group">
            <div class="controls">
            	<label class="checkbox"><input type="checkbox" name="rememberme" value="1" /> remember me</label>
            </div>
        </div>
        
        <input type="hidden" name="nonce" value="<?php echo md5('submit'); ?>" />
        
        <div class="form-actions">
			<input class="btn btn-primary btn-large" type="submit" name="submit" value="Login"/>
            
            <?php if (Conf::spec('allow_register')) : ?>
            <a class="btn btn-large" href="<?php App::the_url_enc('users', 'register'); ?>">Register</a>
            <?php endif; ?>
        </div>
	</fieldset>
</form>


<?php include( App::$conf['dir']['views'] . "/footer.php" ); ?>