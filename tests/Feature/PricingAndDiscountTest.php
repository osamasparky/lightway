<?php
namespace Tests\Feature;

use Tests\TestRunner;
use App\Models\Webinar;

class PricingAndDiscountTest
{
    public function testWebinarPriceAndSpecialOffers()
    {
        $webinar = Webinar::where('status', Webinar::$active)->first();
        if ($webinar) {
            $bestTicket = $webinar->bestTicket();
            TestRunner::assertTrue(is_numeric($bestTicket) || is_null($bestTicket), 'bestTicket must return a numeric or null price');
            
            $specialOffer = $webinar->activeSpecialOffer();
            TestRunner::assertTrue($specialOffer === false || $specialOffer instanceof \App\Models\SpecialOffer, 'activeSpecialOffer must return SpecialOffer or false');
        } else {
            TestRunner::assertTrue(true);
        }
    }
}
