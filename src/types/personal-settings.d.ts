/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 */

/**
 * Configuration injected by lib/Settings/PersonalForm.php through the
 * data-config attribute of the mount point of PersonalSettings.vue.
 */
export interface PersonalSettingsConfig {
  userId: string;
  isTreasurer: boolean;
  toolTipsEnabled: boolean;
  restoreHistory: boolean;
  initialFilterVisibility: boolean;
  directChange: boolean;
  deselectInvisibleMiscRecs: boolean;
  showDisabled: boolean;
  expertMode: boolean;
  financeMode: boolean;
  pageRowsDefault: number;
  debugMode: number;
  wysiwygEditor: string;
  wysiwygOptions: Record<string, { name: string; enabled: boolean }>;
}
