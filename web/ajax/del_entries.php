<?php
declare(strict_types=1);
namespace MRBS;

use MRBS\Form\Form;


// A page designed to be used in Ajax POST calls for bulk deletion of entries.
// It takes an array of ids to be deleted as input.   These are always assumed
// to be single entries.   Returns the number of entries deleted, or some
// kind of string on failure (most likely a login page).
//
// If deleting lots of entries you may need to split the Ajax requests into
// multiple smaller requests in order to avoid exceeding the system limit
// for POST requests, and also the limit on the size of the SQL query once
// the ids are imploded.
//
// Note that:
// (1) the code assumes that you are an admin with powers to delete anything.
//     It checks that you are an admin and so does not bother checking that
//     you have rights in that particular area or room, nor does it check that
//     the proposed deletion conforms to any policy in force.
// (2) email notifications are not sent, even if they are normally configured
//     to be sent.   Sending many thousands of emails in the space of a few
//     seconds could overwhelm many mail servers, or break the usage policies
//     on hosted systems.

require '../defaultincludes.inc';
require_once '../mrbs_sql.inc';

// Check the CSRF token
Form::checkToken();

// Check the user is authorised for this page
checkAuthorised(this_page());

// Check that the user is a booking admin
if (!is_book_admin())
{
  exit;
}

// Get non-standard form variables
$ids = get_form_var('ids', 'string', '[]', INPUT_POST);
// The ids are JSON encoded to avoid hitting the php.ini max_input_vars limit
$ids = json_decode($ids);

// Check that $ids consists of an array of integers, to guard against SQL injection
foreach ($ids as $id)
{
  if (!is_numeric($id) || (intval($id) != $id) || ($id < 0))
  {
    exit;
  }
}


// Everything looks OK - go ahead and delete the entries

// Note on performance.   It is much quicker to delete entries using the
// WHERE id IN method below than looping through mrbsDelEntry().  Testing
// for 100 entries gave 2.5ms for the IN method against 37.6s for the looping
// method - ie approx 15 times faster.   For 1,000 rows the IN method was 19
// times faster.
//
// Because we are not using mrbsDelEntry() we have to delete any orphaned
// rows in the repeat table ourselves - but this does not take long.

$sql = "DELETE FROM " . _tbl('entry') . "
         WHERE id IN (" . implode(',', $ids) . ")";
$result = db()->command($sql);

// And delete any orphaned rows in the repeat table
$sql = "DELETE FROM " . _tbl('repeat') . "
         WHERE id NOT IN (SELECT repeat_id FROM " . _tbl('entry') . ")";
$orphan_result = db()->command($sql);


echo $result;

