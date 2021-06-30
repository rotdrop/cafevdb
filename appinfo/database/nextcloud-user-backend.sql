/*
 * Create some views in order to have the orchestra members and
 * executive board projects as nextcloud users and groups
 */

CREATE OR REPLACE VIEW `NextcloudGroupView` AS
select `p`.`id` AS `gid`,
       `p`.`name` AS `display_name`,
       0 AS `is_admin`
from `Projects` `p`
where `p`.`type` = 'permanent' ;


CREATE OR REPLACE VIEW `NextcloudUserGroupView` AS
select `m`.`user_id_slug` AS `uid`,
       `pp`.`project_id` AS `gid`
from (`ProjectParticipants` `pp`
      left join `Musicians` `m` on(`m`.`id` = `pp`.`musician_id`)) ;


CREATE OR REPLACE VIEW `NextcloudUserView` AS
select `m`.`user_id_slug` AS `uid`,
       `m`.`user_id_slug` AS `username`,
       `m`.`user_passphrase` AS `password`,
       concat_ws(' ', if(`m`.`nick_name` is null
                         or `m`.`nick_name` = '', `m`.`first_name`, `m`.`nick_name`), `m`.`sur_name`) AS `name`,
       `m`.`email` AS `email`,
       NULL AS `quota`,
       NULL AS `home`,
       if(`m`.`deleted` is null, 1, 0) AS `active`,
       if(`m`.`deleted` is null, 0, 1) AS `disabled`,
       0 AS `avatar`,
       NULL AS `salt`
from ((`Musicians` `m`
       left join `ProjectParticipants` `pp` on(`pp`.`musician_id` = `m`.`id`))
      left join `Projects` `p` on(`pp`.`project_id` = `p`.`id`))
where `p`.`type` = 'permanent'
group by `m`.`id` ;
