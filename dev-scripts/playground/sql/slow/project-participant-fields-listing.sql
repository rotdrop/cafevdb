SELECT `PMEtable0`.`id` AS `qf3`,
       PMEjoin1.`name` AS `qf4`,
       `PMEjoin1`.`id` AS `qf4_idx`,
       IF(`PMEtable0`.data_type IN("cloud-file", "cloud-folder"), `PMEtable0`.name, COALESCE(`PMEjoin5`.`content`, `PMEtable0`.name)) AS `qf5`,
       COUNT(DISTINCT PMEjoin2.musician_id) AS `qf6`,
       `PMEtable0`.`deleted` AS `qf7`,
       `PMEtable0`.`deleted` AS `qf7_timestamp`,
       `PMEtable0`.`multiplicity` AS `qf8`,
       `PMEtable0`.`data_type` AS `qf9`,
       `PMEtable0`.`due_date` AS `qf10`,
       `PMEtable0`.`due_date` AS `qf10_timestamp`,
       `PMEtable0`.`deposit_due_date` AS `qf11`,
       `PMEtable0`.`deposit_due_date` AS `qf11_timestamp`,
       GROUP_CONCAT(DISTINCT BIN2UUID(`PMEjoin0`.`key`)
                    ORDER BY `PMEjoin0`.`key` ASC) AS `qf12`,
       GROUP_CONCAT(DISTINCT CONCAT(BIN2UUID(`PMEjoin0`.key), ':', `PMEjoin0`.`label`)
                    ORDER BY `PMEjoin0`.key ASC) AS `qf13`,
       GROUP_CONCAT(DISTINCT CONCAT(BIN2UUID(`PMEjoin0`.key), ':', `PMEjoin0`.`data`)
                    ORDER BY `PMEjoin0`.key ASC) AS `qf14`,
       GROUP_CONCAT(DISTINCT CONCAT(BIN2UUID(`PMEjoin0`.key), ':', `PMEjoin0`.`deposit`)
                    ORDER BY `PMEjoin0`.key ASC) AS `qf15`,
       GROUP_CONCAT(DISTINCT CONCAT(BIN2UUID(`PMEjoin0`.key), ':', `PMEjoin0`.`limit`)
                    ORDER BY `PMEjoin0`.key ASC) AS `qf16`,
       GROUP_CONCAT(DISTINCT CONCAT(BIN2UUID(`PMEjoin0`.key), ':', `PMEjoin0`.`tooltip`)
                    ORDER BY `PMEjoin0`.key ASC) AS `qf17`,
       GROUP_CONCAT(DISTINCT CONCAT(BIN2UUID(`PMEjoin0`.key), ':', `PMEjoin0`.`deleted`)
                    ORDER BY `PMEjoin0`.key ASC) AS `qf18`,
       CONCAT("[", GROUP_CONCAT(DISTINCT JSON_OBJECT("key", BIN2UUID(`PMEjoin0`.key), "label", IF(`PMEtable0`.data_type IN("cloud-file", "cloud-folder"), `PMEjoin0`.label, `PMEjoin0`.l10n_label), "data", `PMEjoin0`.data, "deposit", `PMEjoin0`.deposit, "limit", `PMEjoin0`.`limit`, "tooltip", `PMEjoin0`.l10n_tooltip, "deleted", `PMEjoin0`.deleted)
                                ORDER BY IF(`PMEtable0`.data_type IN("cloud-file", "cloud-folder"), `PMEjoin0`.label, `PMEjoin0`.l10n_label) ASC, `PMEjoin0`.data ASC), "]") AS `qf19`,
       BIN2UUID(`PMEtable0`.default_value) AS `qf27`,
       COALESCE(`PMEjoin31`.`content`, `PMEtable0`.tooltip) AS `qf31`,
       `PMEtable0`.`display_order` AS `qf32`,
       GROUP_CONCAT(DISTINCT PMEjoin33.l10n_tab) AS `qf33`,
       `PMEjoin33`.`original_tab` AS `qf33_idx`,
       `PMEtable0`.`encrypted` AS `qf35`,
       `PMEtable0`.`readers` AS `qf36`,
       `PMEtable0`.`writers` AS `qf37`
FROM `ProjectParticipantFields` AS `PMEtable0`
LEFT JOIN
  (SELECT t1.*,
          COALESCE(jt_label.content, t1.label) AS l10n_label,
          jt_label.content AS translated_label,
          COALESCE(t1.label, jt_label.content) AS original_label,
          t1.label AS untranslated_label,
          COALESCE(jt_tooltip.content, t1.tooltip) AS l10n_tooltip,
          jt_tooltip.content AS translated_tooltip,
          COALESCE(t1.tooltip, jt_tooltip.content) AS original_tooltip,
          t1.tooltip AS untranslated_tooltip
   FROM ProjectParticipantFieldsDataOptions t1
   LEFT JOIN TableFieldTranslations jt_label ON jt_label.locale = 'de_DE'
   AND jt_label.object_class = 'OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantFieldDataOption'
   AND jt_label.field = 'label'
   AND jt_label.foreign_key = CONCAT_WS(' ', t1.field_id, BIN2UUID(t1.key))
   LEFT JOIN TableFieldTranslations jt_tooltip ON jt_tooltip.locale = 'de_DE'
   AND jt_tooltip.object_class = 'OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantFieldDataOption'
   AND jt_tooltip.field = 'tooltip'
   AND jt_tooltip.foreign_key = CONCAT_WS(' ', t1.field_id, BIN2UUID(t1.key))) AS `PMEjoin0` ON (`PMEjoin0`.`field_id` = `PMEtable0`.id)
LEFT JOIN `Projects` AS `PMEjoin1` ON (`PMEjoin1`.`id` = `PMEtable0`.project_id)
LEFT JOIN `ProjectParticipantFieldsData` AS `PMEjoin2` ON (`PMEjoin2`.`field_id` = `PMEtable0`.id
                                                           AND `PMEjoin2`.`project_id` = `PMEtable0`.project_id)
LEFT JOIN `TableFieldTranslations` AS `PMEjoin5` ON (`PMEjoin5`.field = "name"
                                                     AND `PMEjoin5`.foreign_key = `PMEtable0`.id
                                                     AND `PMEjoin5`.object_class = "OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantField"
                                                     AND `PMEjoin5`.locale = "de_DE")
LEFT JOIN
  (SELECT t2.*,
          COALESCE(jt_label.content, t2.label) AS l10n_label,
          jt_label.content AS translated_label,
          COALESCE(t2.label, jt_label.content) AS original_label,
          t2.label AS untranslated_label,
          COALESCE(jt_tooltip.content, t2.tooltip) AS l10n_tooltip,
          jt_tooltip.content AS translated_tooltip,
          COALESCE(t2.tooltip, jt_tooltip.content) AS original_tooltip,
          t2.tooltip AS untranslated_tooltip
   FROM ProjectParticipantFieldsDataOptions t2
   LEFT JOIN TableFieldTranslations jt_label ON jt_label.locale = 'de_DE'
   AND jt_label.object_class = 'OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantFieldDataOption'
   AND jt_label.field = 'label'
   AND jt_label.foreign_key = CONCAT_WS(' ', t2.field_id, BIN2UUID(t2.key))
   LEFT JOIN TableFieldTranslations jt_tooltip ON jt_tooltip.locale = 'de_DE'
   AND jt_tooltip.object_class = 'OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantFieldDataOption'
   AND jt_tooltip.field = 'tooltip'
   AND jt_tooltip.foreign_key = CONCAT_WS(' ', t2.field_id, BIN2UUID(t2.key))) AS `PMEjoin28` ON (`PMEjoin28`.`key` = `PMEtable0`.default_value)
LEFT JOIN
  (SELECT t3.*,
          COALESCE(jt_label.content, t3.label) AS l10n_label,
          jt_label.content AS translated_label,
          COALESCE(t3.label, jt_label.content) AS original_label,
          t3.label AS untranslated_label,
          COALESCE(jt_tooltip.content, t3.tooltip) AS l10n_tooltip,
          jt_tooltip.content AS translated_tooltip,
          COALESCE(t3.tooltip, jt_tooltip.content) AS original_tooltip,
          t3.tooltip AS untranslated_tooltip
   FROM ProjectParticipantFieldsDataOptions t3
   LEFT JOIN TableFieldTranslations jt_label ON jt_label.locale = 'de_DE'
   AND jt_label.object_class = 'OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantFieldDataOption'
   AND jt_label.field = 'label'
   AND jt_label.foreign_key = CONCAT_WS(' ', t3.field_id, BIN2UUID(t3.key))
   LEFT JOIN TableFieldTranslations jt_tooltip ON jt_tooltip.locale = 'de_DE'
   AND jt_tooltip.object_class = 'OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantFieldDataOption'
   AND jt_tooltip.field = 'tooltip'
   AND jt_tooltip.foreign_key = CONCAT_WS(' ', t3.field_id, BIN2UUID(t3.key))) AS `PMEjoin29` ON (`PMEjoin29`.`key` = `PMEtable0`.default_value)
LEFT JOIN
  (SELECT t4.*,
          COALESCE(jt_label.content, t4.label) AS l10n_label,
          jt_label.content AS translated_label,
          COALESCE(t4.label, jt_label.content) AS original_label,
          t4.label AS untranslated_label,
          COALESCE(jt_tooltip.content, t4.tooltip) AS l10n_tooltip,
          jt_tooltip.content AS translated_tooltip,
          COALESCE(t4.tooltip, jt_tooltip.content) AS original_tooltip,
          t4.tooltip AS untranslated_tooltip
   FROM ProjectParticipantFieldsDataOptions t4
   LEFT JOIN TableFieldTranslations jt_label ON jt_label.locale = 'de_DE'
   AND jt_label.object_class = 'OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantFieldDataOption'
   AND jt_label.field = 'label'
   AND jt_label.foreign_key = CONCAT_WS(' ', t4.field_id, BIN2UUID(t4.key))
   LEFT JOIN TableFieldTranslations jt_tooltip ON jt_tooltip.locale = 'de_DE'
   AND jt_tooltip.object_class = 'OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantFieldDataOption'
   AND jt_tooltip.field = 'tooltip'
   AND jt_tooltip.foreign_key = CONCAT_WS(' ', t4.field_id, BIN2UUID(t4.key))) AS `PMEjoin30` ON (`PMEjoin30`.field_id = `PMEtable0`.id)
LEFT JOIN `TableFieldTranslations` AS `PMEjoin31` ON (`PMEjoin31`.field = "tooltip"
                                                      AND `PMEjoin31`.foreign_key = `PMEtable0`.id
                                                      AND `PMEjoin31`.object_class = "OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantField"
                                                      AND `PMEjoin31`.locale = "de_DE")
LEFT JOIN
  (SELECT t5.*,
          COALESCE(jt_tab.content, t5.tab) AS l10n_tab,
          jt_tab.content AS translated_tab,
          COALESCE(t5.tab, jt_tab.content) AS original_tab,
          t5.tab AS untranslated_tab
   FROM ProjectParticipantFields t5
   LEFT JOIN TableFieldTranslations jt_tab ON jt_tab.locale = 'de_DE'
   AND jt_tab.object_class = 'OCA\\CAFEVDB\\Database\\Doctrine\\ORM\\Entities\\ProjectParticipantField'
   AND jt_tab.field = 'tab'
   AND jt_tab.foreign_key = t5.id) AS `PMEjoin33` ON (`PMEjoin33`.id = `PMEtable0`.id)
WHERE PMEtable0.project_id = 18
GROUP BY `PMEtable0`.`id`
ORDER BY PMEjoin1.`name`,
         `PMEtable0`.`display_order` DESC ,
         IF(`PMEtable0`.data_type IN("cloud-file", "cloud-folder"), `PMEtable0`.name, COALESCE(`PMEjoin5`.`content`, `PMEtable0`.name));
