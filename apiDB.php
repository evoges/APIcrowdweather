<?php
include_once 'User.class.php';
include_once 'Location.class.php';
/*
 *  This class will maintain the connection pool to the database
 *  
 */
 
class apiDB { 
/*
 *  These details can be stored in a .conf file
 */
	static $servername = "afrihost.sasscal.org";
	static $DBservername = "localhost";
	static $username = "postgres";
	static $password = "5455c4l_";
	static $dbname = "crowdweather";

	static function validate($username, $password) {
		$conn = apiDB::getConnection();

		$sql = "SELECT password, access FROM cw_user WHERE email = '".$username."' ";
		$result = pg_query($conn, $sql);
		if (($result) && (!empty($password))) {
			$row = pg_fetch_array($result);
			if ($password === $row["password"]) {
				return $row["access"];
			}
		}
		pg_close($conn);
		return -1;
	}

	static function dirname() {
		return substr(strrchr(dirname(__FILE__), "/"), 1);
	}
	
	static function getConnection() {
		// Create connection
		$conn = pg_pconnect("host=".apiDB::$DBservername." dbname=".apiDB::$dbname." user=".apiDB::$username." password=".apiDB::$password);
		if (!$conn) {
			die("Database connection failed. ");
		}
		return $conn;
	}

	static function getUsers($level = 1) {
		$conn = apiDB::getConnection();
		$users = Array();

		$sql = "SELECT * FROM cw_user";
		$result = pg_query($conn, $sql);
		if (count($result) > 0) {
			// output data of each row
			while($row = pg_fetch_array($result)) {
				if ($level > 0) {
					$user = new User();
					$user->email = $row["email"];
					$user->password = $row["password"];
					$user->id = $row["id"];
					$user->locations = apiDB::getUserLocations($user->id, $level - 1); 
				} else {
					$user = $row["id"];
				}
				array_push($users, $user);
			}
		}
		pg_close($conn);
		return $users;
	}

	static function getUserByLocationId($locationid) {
		$conxn = apiDB::getConnection();
		
		$user = new User();
		$sql = "SELECT userid FROM userlocation WHERE locationid = ".$locationid;
		$result = pg_query($conxn, $sql);
		
		if ($result && (pg_num_rows($result) > 0)) {
			$row = pg_fetch_array($result);
			$user = apiDB::getUser($row["userid"]);
		}
		pg_close($conxn);
		return $user;
	}

	static function getUserByEmail($email) {
		$conxn = apiDB::getConnection();
		
		$user = new User();
		$sql = "SELECT id FROM cw_user WHERE email = '".$email."' ";
		$result = pg_query($conxn, $sql);
		
		if (pg_num_rows($result) > 0) {
			$row = pg_fetch_array($result);
			$user = apiDB::getUser($row["id"]);
		}
		pg_close($conxn);
		return $user;
	}

	static function getUser($userid, $level = 1) {
		$conxn = apiDB::getConnection();

		$user = new User();
		$sql = "SELECT * FROM cw_user WHERE id = ".$userid;
		$result = pg_query($conxn, $sql);
		if (pg_num_rows($result) > 0) {
			$row = pg_fetch_array($result);
			if ($level > 0) {
				$user->email = $row["email"];
				$user->password = $row["password"];
				$user->id = $row["id"];
				$user->locations = apiDB::getUserLocations($userid, $level - 1); 
			} else {
				$user = $row["id"];
			}
		} 
		pg_close($conxn);
		return $user;
	}

	static function getUserLocations($userid, $level = 1) {
		if (empty($userid)) {
			return "ERROR: GetUserLocations called without valid userid ";
		}
		$col = empty($_GET["measure"]) ? "rain" : $_GET["measure"];
		if (!in_array($col, array('rain', 'mintemp'))) {
			$col = "rain";
		}
		$conn = apiDB::getConnection();
		$locations = Array();
		$sql = "SELECT l.* FROM cw_user u 
				INNER JOIN userlocation ul on ul.userid = u.id 
				INNER JOIN location l on l.id = ul.locationid 
				LEFT OUTER JOIN ".$col."measurement m on l.id = m.locationid
			WHERE u.id = ".$userid." 
			GROUP BY l.latitude, l.longitude, l.name, l.id
                        ORDER BY max(m.created) DESC ";
		$result = pg_query($conn, $sql);
		if (count($result) > 0) {
			while($row = pg_fetch_array($result)) {
				if ($level > 0) {
					$loc = new Location();  // should any args be parsed here?
					$loc->latitude = $row["latitude"];
					$loc->longitude = $row["longitude"];
					$loc->name = $row["name"];
					$loc->id = $row["id"];
					$loc->userid = $userid;
					$loc->rain = apiDB::getLocationMeasurements($loc->id, $userid, "Rain", $level - 1);
					$loc->mintemp = apiDB::getLocationMeasurements($loc->id, $userid, "Mintemp", $level - 1);
				} else {
					$loc = $row["id"];
				}
				array_push($locations, $loc);
			}
		}
		pg_close($conn);
		return $locations;
	}

	static function getLocations($level = 1) {
		$conn = apiDB::getConnection();
		$locations = Array();

		$sql = "SELECT l.*, ul.userid FROM location l INNER JOIN userlocation ul on l.id = ul.locationid ";
		$result = pg_query($conn, $sql);
		if (count($result) > 0) {
			while($row = pg_fetch_array($result)) {
				if ($level > 0) {
					$loc = new Location();  
					$loc->latitude = $row["latitude"];
					$loc->longitude = $row["longitude"];
					$loc->name = $row["name"];
					$loc->id = $row["id"];
					$loc->userid = $row["userid"];
					$loc->rain = apiDB::getLocationMeasurements($loc->id, $loc->userid, "Rain", $level - 1);
					$loc->mintemp = apiDB::getLocationMeasurements($loc->id, $loc->userid, "Mintemp", $level - 1);
				} else {
					$loc = $row["id"];
				}
				array_push($locations, $loc);
			}
		}
		pg_close($conn);
		return $locations;
	}

	static function getLocation($locationid, $level = 1) {
		$conxn = apiDB::getConnection();

		$location = new Location();
		$sql = "SELECT l.*, ul.userid FROM location l INNER JOIN userlocation ul on l.id = ul.locationid WHERE id = ".$locationid;
		$result = pg_query($conxn, $sql);
		
		if (pg_num_rows($result) > 0) {
			$row = pg_fetch_array($result);
			if ($level > 0) {
				$location->latitude = $row["latitude"];
				$location->longitude = $row["longitude"];
				$location->name = $row["name"];
				$location->id = $row["id"];
				$location->userid = $row["userid"];
				$location->rain = apiDB::getLocationMeasurements($locationid, $row["userid"], "Rain", $level - 1);
				$location->mintemp = apiDB::getLocationMeasurements($locationid, $row["userid"], "Mintemp", $level - 1);
			} else {
				$location = $row["id"];
			}
		} 
		pg_close($conxn);
		return $location;
	}
	
	static function getUserLocation($locationid, $userid, $level = 1) {
		$conxn = apiDB::getConnection();

		$location = new Location();
		$sql = "SELECT * FROM location l INNER JOIN userlocation ul on l.id = ul.locationid WHERE id = ".$locationid." AND userid = ".$userid;
		$result = pg_query($conxn, $sql);
		if (pg_num_rows($result) > 0) {
			$row = pg_fetch_array($result);
			if ($level > 0) {
				$location->latitude = $row["latitude"];
				$location->longitude = $row["longitude"];
				$location->name = $row["name"];
				$location->id = $row["id"];
				$location->userid = $row["userid"];
				$location->rain = apiDB::getLocationMeasurements($locationid, $userid, "Rain", $level - 1);
				$location->mintemp = apiDB::getLocationMeasurements($locationid, $userid, "Mintemp", $level - 1);
			} else {
                                $location = $row["id"];
                        }
		} 
		pg_close($conxn);
		return $location;
	}

	static function getMeasurement($measurementid, $classname, $level = 1) {
		$conxn = apiDB::getConnection();
		$reflector = new ReflectionClass($classname);
		$measurement = $reflector->newInstance();

		$sql = "SELECT * FROM ".$measurement->tableName()." WHERE id = ".$measurementid;
		$result = pg_query($conxn, $sql);
		if ($result) {
			$row = pg_fetch_array($result);
			if ($level > 0) {
				$measurement->id = $row["id"];
				$measurement->reading = $row[$measurement->columnName()];
				$measurement->fromdate = $row["fromdate"];
				$measurement->todate = $row["todate"];
				$measurement->locationid = $row["locationid"];
				$measurement->note = $row["note"];
			} else {
				$measurement = $row["id"];
			}
		} 
		return $measurement;
		pg_close($conxn);
	}
	
	static function getLocationMeasurements($locationid, $userid, $classname, $level = 1) {
		if (empty($locationid)) {
			return "ERROR: GetLocationMeasurements called without valid location id ";
		}
		if (empty($userid)) {
			return "ERROR: GetLocationMeasurements called without valid user id ";
		}
		$conn = apiDB::getConnection();
		$measurements = Array();
		$reflector = new ReflectionClass($classname);
		$msm = $reflector->newInstance();
		$sql = "SELECT m.* FROM ".$msm->tableName()." m WHERE m.locationid = ".$locationid;
		$result = pg_query($conn, $sql);
		if ($result) {
			while($row = pg_fetch_array($result)) {
				if ($level > 0) {
					$msm = $reflector->newInstance();
					$msm->id = $row["id"];
					$msm->reading = $row[$msm->columnName()];
					$msm->fromdate = $row["fromdate"];
					$msm->todate = $row["todate"];
					$msm->locationid = $row["locationid"];
					$msm->userid = $userid;
					$msm->note = $row["note"];
				} else {
					$msm = $row["id"];
				}
				array_push($measurements, $msm);
			}
		}
		pg_close($conn);
		return $measurements;
	}
	
	static function getMaxMeasurementDate($locationid) {
		if (empty($locationid)) {
                        return "ERROR: GetMaxMeasurementDate called without valid location id ";
                }
		$conn = apiDB::getConnection();
		$col = empty($_GET["measure"]) ? "rain" : $_GET["measure"];
                if (!in_array($col, array('rain', 'mintemp'))) {
                        $col = "rain";
                }
                $sql = "SELECT to_char(max(todate + interval '1'), 'YYYY-MM-DD')||'T'||to_char(max(todate + interval '1'),'HH24:MI:SS')  as latestdate FROM ".$col."measurement m WHERE m.locationid = ".$locationid;
		$result = pg_query($conn, $sql);
                if ($result) {
			$row = pg_fetch_array($result);
			return $row["latestdate"];
		}
		return null;
	}
	
	static function addUser(&$user) {
		if (get_class($user) != "User") {
				return "Error, received object other than User";
		}
		if (empty($user->email)) {
			return "Error, no email specified for user";
		}
		if (empty($user->password)) {
			return "Error, no password specified for user";
		}
		$conxn = apiDB::getConnection();
		$sql = "INSERT INTO cw_user (email, password) values ('".$user->email."', '".$user->password."') RETURNING id ";
		$result = pg_query($conxn, $sql);
		if ($result) {
			$rows = pg_affected_rows($result); 
			return $rows." User(s) Added";
		} else {
			return "Error with insert query : ".pg_last_error($conxn);
		}
	}
	
	static function updateUser($userid, $user) {
		if (get_class($user) != "User") {
				return "Error, received object other than User";
		}
		$dbUser = apiDB::getUser($userid);
		
		if (empty($dbUser->id)) {
			return "Error, Invalid User ID for Update";
		}

		$updatestring = "set ";
		$updatestring .= "email = ".(empty($user->email) ? "email" : "'".$user->email."'");
		$updatestring .= ", ";
		$updatestring .= "password = ".(empty($user->password) ? "password" : "'".$user->password."'");

		$conxn = apiDB::getConnection();
		$sql = "UPDATE cw_user ".$updatestring." WHERE id = ".$userid;
		$result = pg_query($conxn, $sql);
		if ($result) {
			$rows = pg_affected_rows($result); 
			return $rows." User(s) updated";
		} else {
			return "Error with User update query : ".pg_last_error($conxn);
		}
	}
	
	static function deleteUser($userid) {
		if (empty($userid)) {
			return "Error, no user id specified for deleting user";
		}
		$conxn = apiDB::getConnection();
		$sql = "DELETE FROM userlocation WHERE userid = ".$userid." ; DELETE FROM cw_user WHERE id = ".$userid." RETURNING id; ";
		$result = pg_query($conxn, $sql);
		if ($result) {
			$rows = pg_fetch_row($result); 
			$deletedid = $rows[0];
			return empty($deletedid) ? "User not found: ".$userid : "User Deleted: ".$userid;
		} else {
			return "Error with delete query for user: ".pg_last_error($conxn);
		}
	}
	
	static function deleteUserByEmail($email) {
		if (empty($email)) {
			return "Error, no email specified for deleting user";
		}
		$conxn = apiDB::getConnection();
		$sql = "DELETE FROM userlocation WHERE userid = (SELECT id FROM cw_user WHERE email = '".$email."') ; DELETE FROM cw_user WHERE email = '".$email."' RETURNING id; ";
		$result = pg_query($conxn, $sql);
		if ($result) {
			$rows = pg_fetch_row($result); 
			$deletedid = $rows[0];
			return empty($deletedid) ? "User not found" : "User Deleted";
		} else {
			return "Error with delete query : ".pg_last_error($conxn);
		}
	}
	
	static function addLocation(&$location) {
		if (get_class($location) != "Location") {
				return "Error, received object other than Location";
		}
		if (empty($location->userid)) {
			return "Error, no userid specified for location";
		}
		if (empty($location->latitude)) {
			return "Error, no latitude specified for location";
		}
		if (empty($location->longitude)) {
			return "Error, no longitude specified for location";
		}
		if (empty($location->name)) {
			return "Error, no name specified for location";
		}
		$conxn = apiDB::getConnection();
		$sql = "INSERT INTO location (latitude, longitude, name) values (".$location->latitude.",".$location->longitude.", '".$location->name."'); ";
		$sql .= "INSERT INTO userlocation (userid, locationid) values (".$location->userid.", (SELECT id FROM location WHERE latitude = ".$location->latitude."  and longitude = ".$location->longitude." and name = '".$location->name."')) RETURNING locationid ";
		
		$result = pg_query($conxn, $sql);
		if ($result) {
			$rows = pg_affected_rows($result); 
			return "Location Added";
		} else { 
			return "Error with insert query : ".pg_last_error($conxn);
		}
	}
	
	static function updateLocation($locationid, $location) {
		if (get_class($location) != "Location") {
				return "Error, received object other than Location for update";
		}
		$dbLocation = apiDB::getLocation($locationid);
		
		if (empty($dbLocation->id)) {
			return "Error, Invalid Location ID for Update";
		}
		
		$conxn = apiDB::getConnection();
		$updatestring = "set ";
		$updatestring .= "latitude = ".(empty($location->latitude) ? "latitude" : "'".$location->latitude."'");
		$updatestring .= ", ";
		$updatestring .= "longitude = ".(empty($location->longitude) ? "longitude" : "'".$location->longitude."'");
		$updatestring .= ", ";
		$updatestring .= "name = ".(empty($location->name) ? "name" : "'".$location->name."'");

		$sql = "UPDATE location ".$updatestring." WHERE id = ".$locationid;
		
		$result = pg_query($conxn, $sql);
		if ($result) {
			$rows = pg_affected_rows($result); 
			return $rows." Location(s) updated";
		} else { 
			return "Error with Location update query : ".pg_last_error($conxn);
		}
	}

	static function deleteLocation($locationid) {
		if (empty($locationid)) {
			return "Error, no location id specified for deleting location";
		}
		$conxn = apiDB::getConnection();
		$sql = "DELETE FROM userlocation WHERE locationid = ".$locationid." ; DELETE FROM measurement WHERE locationid = ".$locationid." ;DELETE FROM location WHERE id = ".$locationid." RETURNING id; ";
		$result = pg_query($conxn, $sql);
		if ($result) {
			$rows = pg_fetch_row($result); 
			$deletedid = $rows[0];
			return empty($deletedid) ? "Location not found: ".$locationid : "Location Deleted: ".$locationid;
		} else {
			return "Error with delete query for location: ".pg_last_error($conxn);
		}
	}
	
	static function addMeasurement($measurement) {
		if (!is_subclass_of ($measurement, "Measurement")) {
				return "Error, received object other than Measurement subclass";
		}
		if (empty($measurement->reading)) {
			return "Error, reading must be specified for measurement";
		}
		if (empty($measurement->locationid)) {
			return "Error, no location id specified for measurement";
		}
		if (empty($measurement->fromdate)) {
			return "Error, no 'from' date specified for measurement";
		}
		if (empty($measurement->todate)) {
			return "Error, no 'to' date specified for measurement";
		}
		$conxn = apiDB::getConnection();
		$sql = "INSERT INTO ".$measurement->tableName()." (fromdate, todate, locationid, ".$measurement->columnName();
		$sql .= empty($measurement->note)? "" : ", note";
		$sql .= ") VALUES (TIMESTAMP '".$measurement->fromdate."', TIMESTAMP '".$measurement->todate."', ".$measurement->locationid.", ".$measurement->reading;
		$sql .= empty($measurement->note)? "" : ", '".$measurement->note."'";
		$sql .= "); ";
	error_log("==========fromDate ".$measurement->fromdate);	
		$result = pg_query($conxn, $sql);
		if ($result) {
			$rows = pg_affected_rows($result); 
			return "Measurement Added: \"".$measurement->columnName()."\"";
		} else { 
			return "Error with ".$measurement->columnName()." measurement insert query : ".pg_last_error($conxn);
		}
	}
	
	static function updateMeasurement($measurementid, $measurement) {
		if (!is_subclass_of ($measurement, "Measurement")) {
				return "Error, received object other than Measurement subclass";
		}
		$dbMeasurement = apiDB::getMeasurement($measurementid, get_class($measurement));
		
		if (empty($dbMeasurement->id)) {
			return "Error, Invalid Measurement ID for Update";
		}
		
		$conxn = apiDB::getConnection();
		$updatestring = " set ";
		$updatestring .= $measurement->columnName()." = ".(empty($measurement->reading) ? $measurement->columnName() : $measurement->reading);
		$updatestring .= ", ";
		$updatestring .= "fromdate = ".(empty($measurement->fromdate) ? "fromdate" : "'".$measurement->fromdate."'");
		$updatestring .= ", ";
		$updatestring .= "todate = ".(empty($measurement->todate) ? "todate" : "'".$measurement->todate."'");
		$updatestring .= ", ";
		$updatestring .= "locationid = ".(empty($measurement->locationid) ? "locationid" : $measurement->locationid);
		$updatestring .= ", ";
		$updatestring .= "note = ".(empty($measurement->note) ? "note" : "'".$measurement->note."'");

		$sql = "UPDATE ".$measurement->tableName()." ".$updatestring." WHERE id = ".$measurementid;
		$result = pg_query($conxn, $sql);
		if ($result) {
			$rows = pg_affected_rows($result); 
			return $rows." ".$measurement->columnName()." measurement(s) updated";
		} else { 
			return "Error with ".$measurement->columnName()." measurement update query : ".pg_last_error($conxn);
		}
	}

	static function deleteMeasurement($measurementid, $table) {
		if (empty($measurementid)) {
			return "Error, no measurement id specified for deleting measurement";
		}
		$conxn = apiDB::getConnection();
		$sql = "DELETE FROM $table WHERE id = ".$measurementid." RETURNING id; ";
		$result = pg_query($conxn, $sql);
		if ($result) {
			$rows = pg_fetch_row($result); 
			$deletedid = $rows[0];
			return empty($deletedid) ? "Measurement not found in $table: ".$measurementid : "Measurement deleted from $table: ".$measurementid;
		} else {
			return "Error with delete query for Measurement in $table: ".pg_last_error($conxn);
		}
	}

 	static function getLatestMeasurements($userid) {
		if (empty($userid)) {
			return "Error, no user id detected or specified for latest measurements";
		}
		$conxn = apiDB::getConnection();
		$sql = "SELECT l.name, l.id AS locationid, r.id, r.rain AS measurement, r.todate as todate, r.created AS crtime, 'rain' AS mtype 
                        FROM location l INNER JOIN userlocation ul ON l.id = ul.locationid 
	                                LEFT OUTER JOIN rainmeasurement r ON r.locationid = ul.locationid
                        WHERE userid = ".$userid." AND r.created IS NOT NULL
                        UNION
                        SELECT l.name, l.id AS locationid, m.id, m.mintemp AS measurement, m.todate as todate, m.created AS crtime, 'mintemp' AS mtype 
                        FROM location l INNER JOIN userlocation ul ON l.id = ul.locationid 
	                        LEFT OUTER JOIN mintempmeasurement m ON m.locationid = ul.locationid
                        WHERE userid = ".$userid." AND m.created IS NOT NULL
                        ORDER BY crtime DESC
                        LIMIT 20 ";
		$result = pg_query($conxn, $sql);
                $results_array = Array();
		if ($result) {
                        while($row = pg_fetch_array($result)) {
                            $item = Array();
                            $item["locationid"] = $row["locationid"];
                            $item["locationname"] = $row["name"];
                            $item["rainid"] = $row["mtype"] == "rain" ? $row["id"] : "";
                            $item["mintempid"] =$row["mtype"] == "mintemp" ? $row["id"] : "";
                            $item["rain"] = $row["mtype"] == "rain" ? $row["measurement"] : "";
                            $item["mintemp"] = $row["mtype"] == "mintemp" ? $row["measurement"] : "";
                            $item["raintodate"] = $row["mtype"] == "rain" ? $row["todate"] : "";
                            $item["mintemptodate"] = $row["mtype"] == "mintemp" ? $row["todate"] : "";
                            //$item[""] = $row[""];
                            array_push($results_array, $item);
                        }
                }
                return $results_array;
        }
}
?>
