<?php

use Illuminate\Support\Facades\Route;
use Ethernick\ActivityPubEvents\Http\Controllers\EventCpController;

Route::get('activitypub/events', 'Ethernick\ActivityPubEvents\Http\Controllers\EventCpController@index')->name('activitypub.events.index');
Route::post('activitypub/events', 'Ethernick\ActivityPubEvents\Http\Controllers\EventCpController@store')->name('activitypub.events.store');
Route::post('activitypub/events/{id}/rsvp', 'Ethernick\ActivityPubEvents\Http\Controllers\EventCpController@rsvp')->name('activitypub.events.rsvp');

