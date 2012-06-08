<?php
ignore_user_abort(true);
set_time_limit(0);
include_once 'config.inc.php';

$etc = new Etc;
$auth = new Authorize;

if(!$_GET['database'] || '' == $_GET['database'])
{
	die($lang['dieTableChoose']);
}
else
{

	echo $_GET['database'].' --  <a href=dbStructure.php?database='.$_GET['database'].' target="right">'.$lang['back'].'</a><br /><br />';

	include_once 'templates/sql_query_navi.html';
	include_once 'templates/style.css';
	echo "<br /><br />";
	
	$transport = new TSocket(HOST, PORT);
	$protocol = new TBinaryProtocol($transport);
	$client = new ThriftHiveClient($protocol);
	
	$transport->open();

	$sql = 'use `'.$_GET['database'].'`';
	//echo $sql.'<br /><br />';
	$client->execute($sql);
	//$client->fetchOne();
	
	$sql = 'desc '.$_GET['table'];
	$client->execute($sql);
	$array_desc_table = $client->fetchAll();
	//get table description and explode the desc into a multi-dimensional array
	//��ȡ��˵�����������ά����$array_desc_desc
	$i = 0;
	while ('' != @$array_desc_table[$i])
	{
		$array_desc = explode('	',$array_desc_table[$i]);
		$array_desc_desc['name'][$i] = $array_desc[0];
		$array_desc_desc['type'][$i] = $array_desc[1];
		$array_desc_desc['comment'][$i] = $array_desc[2];
		$i++;
	}
	//var_dump($array_desc_desc);

	if(!@$_POST['sql'] || '' == @$_POST['sql'])
	{
		echo '<table border=1 cellspacing=1 cellpadding=3>';
		$i = 0;
		foreach ($array_desc_desc as $value)
		{
			if(0 == $i)
			{
				$color = "bgcolor=\"#FFFF99\"";
			}
			else
			{
				$color = "bgcolor=\"#99FFFF\"";
			}
			echo '<tr '.$color.'>';
			foreach($value as $v)
			{
				echo '<td>'.$v.'</td>';
				$i++;
			}
			echo '</tr>';
		}
		echo '</table>';
		include_once 'templates/sql_query.html';
	}
	else
	{
		/*if(preg_match("/( {0,}select +\* +from)/i",@$_POST['sql']) && !preg_match("/limit/i", @$_POST['sql']) && !preg_match("/where/i", @$_POST['sql']))# if select * from with no limit died.
		{
			die($lang['forceLimit']);
		}*/
		//elseif(!preg_match("/limit/i", @$_POST['sql']))
		//{
			$sha1 = $etc->FingerPrintMake();
			
			#auth if have enough privileges to do hql query
			$sql = $auth->AuthSql($_SESSION['onlydb'],@$_POST['sql']);
			if($sql == FALSE)
			{
				die("<script>alert('".$lang['permissionDenied']."');history.back()</script>");
			}
			#auth if have enough privileges to do hql query
			
			# Get map red Slots which the current user can use 
			/*$slots = $auth->AuthMapReduceSlots($env["privFile"],$_SESSION['username'],$_SESSION['password']);
			$slots = explode(",",$slots);
			$mslots = $slots[0];
			$rslots = $slots[1];
			
			if($mslots != '0')
			{
				$mslots = "set mapred.map.tasks=".$mslots."; ";
			}
			else
			{
				$mslots = "";
			}
			
			if($rslots != '0')
			{
				$rslots = "set mapred.reduce.tasks=".$rslots."; ";
			}
			else
			{
				$rslots = ""; 
			}
			
			$slots = $mslots.$rslots;*/
			# Get map red Slots which the current user can use 
			$slots = "";
			if(substr($sql,-1) != ";")
			{
				$sql = "use ".@$_POST['database'].";".$slots.$sql.";";
			}
			else
			{
				$sql = "use ".@$_POST['database'].";".$slots.$sql;
			}
			
			#log sql action
			//$logfile = $env['logs_path'].$_SESSION['username']."_".$sha1.".log";
			//$etc->LogAction($logfile,"w",$sql."\n");
			#

			#$path = $env['http_url']."?time=".$sha1."&query=".urlencode($sql,$key);
			$cookie = sha1($mtime);

			echo "
			<script>
			function getReult()
			{
				document.getElementById('stderr').src='refresh.php?str=".$sha1."';
			}
			</script>
			";
			
			echo "<body bgcolor=#EFEFEF onload=\"ajaxRequest('cliQuery.php?time=".$sha1."&query=".rawurlencode($sql)."' , getReult)\">";
			echo "<input type=button value=\"".$lang['getResult']."\" onclick=\"window.open('getResult.php?str=".$sha1."')\">";
			echo "<br><br>".$lang['fingerprintOfMapReduce']." ".$sha1;
			echo "<br><br>";
			echo "SQL: ".$sql;
			echo "<br><br>";
			echo "<iframe id=stderr width=600 height=400 align=left src=refresh.php?str=".$sha1."></iframe><br><br>";
		//}
		/*else
		{
			$timer = new Timer;
			$timer->start();
			$sql = $_POST['sql'];
			echo $sql.'<br /><br />';
			
			#auth if have enough privileges to do hql query
			$sql = $auth->AuthSql($_SESSION['onlydb'],@$_POST['sql']);
			if($sql == FALSE)
			{
				die("<script>alert('".$lang['permissionDenied']."');history.back()</script>");
			}
			#auth if have enough privileges to do hql query
			
			$sha1 = $etc->FingerPrintMake();
			
			#log sql
			$logfile = $env['logs_path'].$_SESSION['username']."_".$sha1.".log";
			$etc->LogAction($logfile,"w",$sql."\n");
			
			$sql = $slots.$sql;
			$client->execute($sql);
			$array = $client->fetchAll();

			#construct table desc table
			echo "<table border=1 cellspacing=1 cellpadding=3>\n";
			$i = 0;
			foreach ($array_desc_desc as $value)
			{
				if(0 == $i)
				{
					$color = "bgcolor=\"#FFFF99\"";
				}
				else
				{
					$color = "bgcolor=\"#99FFFF\"";
				}
				echo "<tr ".$color.">\n";
				foreach($value as $v)
				{
					echo "<td>".$v."</td>\n";
					$i++;
				}
				echo "</tr>\n";
				$i++;
			}
			#construct result table
			$i = 0;		
			while ('' != @$array[$i])
			{
				if(($i % 2) == 0)
				{
					$color = "bgcolor=\"".$env['trColor1']."\"";
				}
				else
				{
					$color = "bgcolor=\"".$env['trColor2']."\"";
				}
				echo "<tr ".$color.">\n";
				$arr = explode('	',$array[$i]);
				foreach ($arr as $key => $value)
				{
						$value = str_replace('<','&lt;',$value);
						$value = str_replace('>','&gt;',$value);
						echo "<td>".$value."</td>\n";
				}
				#echo '<td>'.$array[$i].'</td>';
				echo "</tr>\n";
				$i++;
			}
			echo "</table>\n";
			include_once 'templates/sql_query.html';
			$timer->stop();
			echo 'Excution time: '.$timer->spent().'s';
			unset($timer);
		}*/
	}
	$transport->close();
}
?>