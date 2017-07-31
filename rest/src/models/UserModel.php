<?php

/**
 * Kelas entitas pengguna
 * @author Nur Hardyanto
 *
 */
class UserModel {
	
	const TABLE_USER = 'users';
	/**
	 * Koneksi database MySQLi
	 * @var object
	 */
	private $_db;
	
	public function __construct($dbConnection) {
		$this->_db = $dbConnection;
	}
	
	/**
	 * Hash password pada sistem
	 * @param string $newPassword String password
	 * @return string String hash
	 */
	private function _hash_password($newPassword) {
		// == Generate hash untuk password baru
		$cost = 10;
		$salt = strtr(base64_encode(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM)), '+', '.');
		$salt = sprintf("$2a$%02d$", $cost) . $salt;
		$passwordHash = crypt($newPassword, $salt);
	
		return $passwordHash;
	}
	
	/**
	 * Fetch users
	 * @param int $minPrivilege Level privilege minimal. -1 untuk mengambil semua
	 * @return null|array NULL jika error, jika sukses kembali array objek
	 */
	function get_users($minPrivilege = -1) {
		$condition = array();
		
		if ($minPrivilege > 0) $condition['privilege_level'] = _db_to_query($minPrivilege, $this->_db);
		
		$selectQuery = db_select($this::TABLE_USER, $condition);
		$queryResult = mysqli_query($this->_db, $selectQuery);
	
		if (!$queryResult) return null;
		$index = 0;
		$listRecord = array();
	
		while ($row = mysqli_fetch_array($queryResult, MYSQLI_ASSOC)) {
			$index = $row['id_route'];
			$listRecord[$index] = $row;
		}
		return $listRecord;
	}
	
	/**
	 * Ambil data record user dengan id tertentu
	 * @param integer $idUser User ID
	 * @return array|FALSE Kembali array asosiatif dari record, atau FALSE jika gagal.
	 */
	function get_user_by_id($idUser) {
		$selectQuery = db_select($this::TABLE_USER, array('id_user' => $idUser));
	
		$result = mysqli_query($this->_db, $selectQuery);
	
		$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
		return $row;
	}
	
	/**
	 * Ambil data record user berdasar email
	 * @param string $emailUser Email user
	 * @return array|FALSE Kembali array asosiatif dari record, atau FALSE jika gagal.
	 */
	function get_user_by_email($emailUser) {
		$sanitizedEmail = _db_to_query($emailUser, $this->_db);
		$selectQuery = db_select($this::TABLE_USER, array('email' => $sanitizedEmail));
	
		$result = mysqli_query($this->_db, $selectQuery);
		$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
		return $row;
	}
	
	/**
	 * Ambil data record user berdasar kunci token
	 * @param string $emailUser Email user
	 * @return array|FALSE Kembali array asosiatif dari record, atau FALSE jika gagal.
	 */
	function get_user_by_token($tokenUser) {
		$sanitizedToken = _db_to_query($tokenUser, $this->_db);
		$selectQuery = db_select($this::TABLE_USER, array('token' => $sanitizedToken));
	
		$result = mysqli_query($this->_db, $selectQuery);
		$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
		return $row;
	}
	
	/**
	 * Simpan record user
	 * @param array $userData Data user, field sesuai database
	 * @param integer $userId ID user. -1 untuk insert
	 * @return int|NULL id user baru jika berhasil, sebaliknya NULL
	 */
	function save_user($userData, $idUser = -1) {
		$userFields = array();
		foreach ($userData as $propKey => $propValue) {
			$userFields[$propKey] = $propValue;
		}
		$saveQuery = "";
		if ($idUser > 0) {
			$saveQuery = db_update($this::TABLE_USER, $userData, array('id_user' => $idUser));
		} else {
			if (!isset($userFields['date_created']))
				$userFields['date_created'] = _db_to_query(date('Y-m-d H:i:s'), $this->_db);
				$saveQuery = db_insert_into($this::TABLE_USER, $userFields);
		}
	
		$queryResult = mysqli_query($this->_db, $saveQuery);
		if ($queryResult) {
			if ($idUser > 0) {
				return $idUser;
			} else {
				$newId = mysqli_insert_id($this->_db);
				return $newId;
			}
		} else return null;
	}
}