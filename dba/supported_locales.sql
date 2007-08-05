TRUNCATE supported_locales;

-- I should be able to find people to translate into these base locales
INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )
    VALUES( 'en_NZ', 'English', 'English' );
INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )
    VALUES( 'de_DE', 'German',  'Deutsch' );
INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )
    VALUES( 'es_AR', 'Argentinian Spanish', 'Español (AR)' );
INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )
    VALUES( 'es_ES', 'Spanish (Spain)', 'Español (ES)' );
INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )
    VALUES( 'es_MX', 'Mexican Spanish', 'Español (MX)' );
INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )
    VALUES( 'fr_FR', 'French',  'Français' );
INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )
    VALUES( 'ru_RU', 'Russian',  'Русский' );
INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )
    VALUES( 'nl_NL', 'Netherlands',  'Nederlands' );
INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )
    VALUES( 'pl_PL', 'Polish',  'Polski' );
INSERT INTO supported_locales ( locale, locale_name_en, locale_name_locale )
    VALUES( 'hu_HU', 'Hungarian',  'Magyar' );
