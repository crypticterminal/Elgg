<?php

use Phinx\Migration\AbstractMigration;

class DenormalizeMetastrings extends AbstractMigration {
	/**
	 * Denormlize metastrings
	 *
	 * - Add "name" and "value" columns to "metadata" table
	 * - Populate these new columns from metastrings table by id
	 * - Add indexes
	 * - Drop "metastrings" table
	 */
	public function up() {

		if (!$this->hasTable('metastrings')) {
			return;
		}

		$tables = [
			'metadata',
			'annotations',
		];

		foreach ($tables as $table) {
			if (!$this->hasTable($table)) {
				continue;
			}

			$table = $this->table($table);

			if (!$table->hasColumn('value')) {
				$table->addColumn('value', 'text', [
					'null' => false,
					'after' => 'entity_guid',
				]);
			}

			if (!$table->hasIndex('value')) {
				$table->addIndex(['value'], [
					'name' => "value",
					'unique' => false,
					'limit' => 50,
				]);
			}

			if (!$table->hasColumn('name')) {
				$table->addColumn('name', 'text', [
					'null' => false,
					'after' => 'entity_guid',
				]);
			}

			if (!$table->hasIndex('name')) {
				$table->addIndex(['name'], [
					'name' => "name",
					'unique' => false,
					'limit' => 50,
				]);
			}

			$table->save();

			if ($table->hasColumn('name_id') && $table->hasColumn('value_id')) {
				$prefix = $this->getAdapter()->getOption('table_prefix');

				// move in all metastrings
				$this->query("
					UPDATE {$prefix}{$table->getName()} n_table
					INNER JOIN {$prefix}metastrings msn ON n_table.name_id = msn.id
					INNER JOIN {$prefix}metastrings msv ON n_table.value_id = msv.id
					SET n_table.name = msn.string,
						n_table.value = msv.string	
				");

				// drop columns and indexes

				if ($table->hasIndex('name_id')) {
					$table->removeIndexByName('name_id');
				}

				if ($table->hasIndex('value_id')) {
					$table->removeIndexByName('value_id');
				}

				$table->removeColumn('name_id');
				$table->removeColumn('value_id');
			}

			$table->save();
		}

		$this->dropTable('metastrings');
	}

	/**
	 * Normalize metastrings
	 *
	 * CREATE TABLE `prefix_metastrings` (
	 * `id` int(11) NOT NULL AUTO_INCREMENT,
	 * `string` text NOT NULL,
	 * PRIMARY KEY (`id`),
	 * KEY `string` (`string`(50))
	 * ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
	 */
	public function down() {

		if ($this->hasTable('metastrtings')) {
			return;
		}

		$table = $this->table('metastrings', [
			'engine' => 'MyISAM',
			'encoding' => "utf8",
			'collation' => "utf8_general_ci",
		]);

		$table->addColumn('string', 'text', [
			'null' => false,
		]);

		$table->addIndex(['string'], [
			'name' => 'string',
			'limit' => 50,
		]);

		$table->save();

		$prefix = $this->getAdapter()->getOption('table_prefix');

		foreach ([
					 'metadata',
					 'annotations'
				 ] as $table) {
			$table = $this->table($table);

			if (!$table->hasColumn('name_id')) {
				$table->addColumn('name_id', 'integer', [
					'null' => false,
					'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
					'precision' => 11,
				]);
			}

			if (!$table->hasColumn('value_id')) {
				$table->addColumn('value_id', 'integer', [
					'null' => false,
					'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
					'precision' => 11,
				]);
			}

			if (!$table->hasIndex('name_id')) {
				$table->addIndex(['name_id'], [
					'name' => 'name_id',
				]);
			}

			if (!$table->hasIndex(['value_id'])) {
				$table->addIndex(['value_id'], [
					'name' => 'value_id',
				]);
			}

			$table->save();

			$rows = $this->fetchAll("
				SELECT name, value 
				FROM {$prefix}{$table->getName()}
			");

			foreach ($rows as $row) {
				$this->insert('metastrings', [
					['string' => $row['name']],
					['string' => $row['value']],
				]);
			}

			// move in all metastrings
			$this->query("
				UPDATE {$prefix}{$table->getName()} n_table
				INNER JOIN {$prefix}metastrings msn ON n_table.name = msn.string
				INNER JOIN {$prefix}metastrings msv ON n_table.value = msv.string
				SET n_table.name_id = msn.id,
					n_table.value_id = msv.id	
			");

			if ($table->hasIndex('name')) {
				$table->removeIndexByName('name');
			}

			if ($table->hasIndex('value')) {
				$table->removeIndexByName('value');
			}

			if ($table->hasColumn('name')) {
				$table->removeColumn('name');
			}

			if ($table->hasColumn('value')) {
				$table->removeColumn('value');
			}
		}
	}


}
