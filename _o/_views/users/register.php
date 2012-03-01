<?php include( App::$conf['dir']['views'] . "/header.php" ); ?>

<form class="form-horizontal" action="" method="post">
	<legend>Register</legend>
    
	<fieldset>
		<?php App::the_errors($result); ?>
		
        <div class="control-group">
        	<label class="control-label">Username:</label> 
            <div class="controls">
            	<input type="text" name="username" value="<?php echo htmlentities($this->params['username']); ?>" />
            </div>
        </div>
		
        <div class="control-group">
        	<label class="control-label">Password: </label>
            <div class="controls">
            	<input type="password" name="password"/>
            </div>
        </div>
        
        <div class="control-group">
        	<label class="control-label">Email: </label>
            <div class="controls">
            	<input type="email" name="email" value="<?php echo htmlentities($this->params['email']); ?>" />
            </div>
        </div>

        <input type="hidden" name="nonce" value="<?php echo md5('submit'); ?>" />
        
        <div class="form-actions">
			<input class="btn btn-primary btn-large" type="submit" name="submit" value="Register"/>
        </div>
	</fieldset>
</form>


<?php include( App::$conf['dir']['views'] . "/footer.php" ); ?>