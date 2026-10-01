<?php

/*
 * Replies written by the system, not the model, for turns the model could
 * not complete. Each one keeps the emergency advice: a patient must never be
 * left with nothing because of a technical problem.
 */

return [
    'fallback' => [
        'unavailable' => 'Sorry, a technical problem stopped me from replying. Please send your message again in a moment. If your symptoms are severe or you think this may be an emergency, do not wait for me: call your local emergency number or go to the nearest emergency department now.',
        'refusal' => 'I am not able to help with that request. I can help you decide what kind of care to seek and find doctors or hospitals in the HealTrip directory. If this is a medical emergency, call your local emergency number now.',
        'incomplete' => 'Sorry, I could not finish working that out. Could you tell me again, in a sentence or two, what you need help with? If you think this may be an emergency, call your local emergency number or go to the nearest emergency department now.',
        'ungrounded' => 'I could not verify part of my answer against the HealTrip directory, so I have held it back rather than risk giving you wrong details. Please ask again, or tell me the specialty and city you are interested in and I will search the directory. If this is urgent, call your local emergency number now.',
    ],
];
