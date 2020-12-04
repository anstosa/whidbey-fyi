<?php

namespace Wikibase\DataAccess\Tests;

use Wikibase\DataAccess\DataAccessSettings;

/**
 * Todo: factory methods should return partially mocked objects or mock builder instances instead
 * using PHPUnit\Framework\MockObject\Generator or PHPUnit\Framework\MockObject\MockBuilder.
 */
class DataAccessSettingsFactory {

	public static function anySettings(): DataAccessSettings {
		return new DataAccessSettings(
			100,
			true,
			false,
			DataAccessSettings::PROPERTY_TERMS_UNNORMALIZED,
			DataAccessSettings::ITEM_TERMS_UNNORMALIZED_STAGE_ONLY,
			MIGRATION_OLD,
			MIGRATION_OLD
		);
	}

}
