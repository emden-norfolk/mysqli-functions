<?php

function mysqli_fetch_assoc_per_table(mysqli_result $result, $instruction) {
	$row = $result->fetch_assoc();
	if (!$row) return false;
	$fields = $result->fetch_fields();

	$groups = array();
	foreach (explode(',', $instruction) as $arg) {

		$group = array();
		foreach (explode('|', $arg) as $table) {
			foreach ($fields as $field) {
				if ($field->table == $table) {
					$group[$field->name] = $row[$field->name];
				}
			}
		}

		$groups[] = $group;
	}

	return $groups;
}

function mysqli_bind_params(mysqli_stmt $stmt, array $params) {
	$types = '';
	$refs = array();
	foreach ($params as $i => $param) {
		if (is_int($param)) {
			$types .= 'i';
		} else {
			$types .= 's';
		}

		if (strnatcmp(phpversion(), '5.3') >= 0) {
			$refs[] = &$params[$i];
		} else {
			$refs[] = $params[$i];
		}
	}

	array_unshift($refs, $types);
	return call_user_func_array(array($stmt, 'bind_param'), $refs);
}

function mysqli_type_integer($type) {
	if ($type == MYSQLI_TYPE_BIT) return true;
	if ($type == MYSQLI_TYPE_TINY) return true;
	if ($type == MYSQLI_TYPE_SHORT) return true;
	if ($type == MYSQLI_TYPE_LONG) return true;
	if ($type == MYSQLI_TYPE_LONGLONG) return true;
	if ($type == MYSQLI_TYPE_INT24) return true;
	return false;
}

function mysqli_type_float($type) {
	if ($type == MYSQLI_TYPE_DECIMAL) return true;
	if ($type == MYSQLI_TYPE_NEWDECIMAL) return true;
	if ($type == MYSQLI_TYPE_FLOAT) return true;
	if ($type == MYSQLI_TYPE_DOUBLE) return true;
	return false;
}

function mysqli_cast_row(mysqli_result $query, $row, $cast_float = false) {
	if ($row === null) return null;

	foreach ($query->fetch_fields() as $field) {
		if ($row[$field->name] === null) continue;

		if (mysqli_type_integer($field->type)) {
			$row[$field->name] = (int)$row[$field->name];
		}

		if ($cast_float && mysqli_type_float($field->type)) {
			$row[$field->name] = (float)$row[$field->name];
		}
	}
	return $row;
}

function mysqli_placeholders(mysqli $link, array &$params, $set = false, $glue = ',') {
	$placeholders = array();
	foreach ($params as $name => $value) {
		if (is_int($value)) {
			$placeholders[] = $set ? "$name = %d" : '%d';
		}
		if (is_float($value)) {
			$placeholders[] = $set ? "$name = %f" : '%f';
		}
		if (is_string($value)) {
			$placeholders[] = $set ? "$name = '%s'" : '\'%s\'';
			$params[$name] = $link->real_escape_string($value);
		}
		if (is_null($value)) {
			$placeholders[] = $set ? "$name = NULL" : 'NULL';
			unset($params[$name]);
		}
	}
	return implode($glue, $placeholders);
}

function mysqli_replace(mysqli $link, $table, array $params) {
    return mysqli_insert($link, $table, $params, 'REPLACE');
}

function mysqli_insert(mysqli $link, $table, array $params, $insert = 'INSERT') {
	$sql = "$insert INTO `$table` (". implode(',', array_keys($params)) .") ";
	$sql .= "VALUES (". mysqli_placeholders($link, $params) .")";

	$query = $link->query(call_user_func_array('sprintf', array_merge([$sql], $params)));
	if (!$query) return false;
	return $link->insert_id ? $link->insert_id : true;
}

function mysqli_update(mysqli $link, $table, array $params, array $where, $limit = null) {
	$sql = "
		UPDATE `$table`
		SET ". mysqli_placeholders($link, $params, true) ."
		WHERE ". mysqli_placeholders($link, $where, true, ' AND ');

	if ($limit) {
		$sql .= " LIMIT $limit";
	}

	return $link->query(call_user_func_array('sprintf', array_merge([$sql], array_values($params), array_values($where))));
}

function mysqli_select(mysqli $link, $table, array $where = array(), $order_by = null) {
	$sql = "SELECT * FROM `$table`";
	if ($where)
		$sql .= ' WHERE '. mysqli_placeholders($link, $where, true, ' AND ');

	if ($order_by !== null)
		$sql .= " ORDER BY $order_by";

	return $link->query(call_user_func_array('sprintf', array_merge([$sql], $where)));
}

function mysqli_delete(mysqli $link, $table, array $where) {
	$sql = "
		DELETE
		FROM `$table`
		WHERE ". mysqli_placeholders($link, $where, true, ' AND ');

	return $link->query(call_user_func_array('sprintf', array_merge([$sql], $where)));
}

function mysqli_describe_columns(mysqli $link, $table) {
	$query = $link->query("DESCRIBE `$table`");
	if (!$query) return false;

	$columns = array();
	while ($col = $query->fetch_assoc()) {
		$columns[] = $col['Field'];
	}
	return $columns;
}

function mysqli_enum_vals(mysqli $link, $table, $field) {
	$query = $link->query('SHOW COLUMNS FROM `'. $table .'` WHERE Field = \''. $link->real_escape_string($field) .'\'');
	if (!$query) {
		return false;
	}
	$info = $query->fetch_assoc();
	preg_match("/^enum\(\'(.*)\'\)$/", $info['Type'], $matches);
	if (!isset($matches[1])) return array();
	return explode("','", $matches[1]);
}
