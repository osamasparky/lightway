<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mixins\Installment\InstallmentPlans;
use App\Models\AdvertisingBanner;
use App\Models\Blog;
use App\Models\Bundle;
use App\Models\FeatureWebinar;
use App\Models\HomePageStatistic;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SpecialOffer;
use App\Models\Subscribe;
use App\Models\Ticket;
use App\Models\TrendCategory;
use App\Models\UpcomingCourse;
use App\Models\Webinar;
use App\Models\Testimonial;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        $homeSections = HomeSection::orderBy('order', 'asc')->get();
        $selectedSectionsName = $homeSections->pluck('name')->toArray();

        $webinarRelations = [
            'translations',
            'category.translations',
            'category.category',
            'specialOffers',
            'productBadgeContent.badge.translations',
            'teacher' => function ($qu) {
                $qu->select('id', 'full_name', 'avatar');
            },
            'reviews' => function ($query) {
                $query->where('status', 'active');
            },
            'tickets',
            'feature'
        ];

        $featureWebinars = null;
        if (in_array(HomeSection::$featured_classes, $selectedSectionsName)) {
            $featureWebinars = FeatureWebinar::whereIn('page', ['home', 'home_categories'])
                ->where('status', 'publish')
                ->whereHas('webinar', function ($query) {
                    $query->where('status', Webinar::$active);
                })
                ->with([
                    'webinar' => function ($query) use ($webinarRelations) {
                        $query->with($webinarRelations);
                    }
                ])
                ->orderBy('updated_at', 'desc')
                ->get();
        }

        if (in_array(HomeSection::$latest_classes, $selectedSectionsName)) {
            $latestWebinars = Webinar::where('status', Webinar::$active)
                ->where('private', false)
                ->orderBy('updated_at', 'desc')
                ->with($webinarRelations)
                ->limit(6)
                ->get();
        }

        if (in_array(HomeSection::$latest_bundles, $selectedSectionsName)) {
            $latestBundles = Bundle::where('status', Webinar::$active)
                ->orderBy('updated_at', 'desc')
                ->with([
                    'translations',
                    'category.translations',
                    'specialOffers',
                    'productBadgeContent.badge.translations',
                    'teacher' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    },
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'tickets',
                ])
                ->limit(6)
                ->get();
        }

        if (in_array(HomeSection::$upcoming_courses, $selectedSectionsName)) {
            $upcomingCourses = UpcomingCourse::where('status', Webinar::$active)
                ->orderBy('created_at', 'desc')
                ->with([
                    'translations',
                    'category.translations',
                    'productBadgeContent.badge.translations',
                    'teacher' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    }
                ])
                ->limit(6)
                ->get();
        }

        if (in_array(HomeSection::$best_sellers, $selectedSectionsName)) {
            $bestSaleWebinarsIds = Sale::whereNotNull('webinar_id')
                ->select(DB::raw('COUNT(id) as cnt,webinar_id'))
                ->groupBy('webinar_id')
                ->orderBy('cnt', 'DESC')
                ->limit(6)
                ->pluck('webinar_id')
                ->toArray();

            $bestSaleWebinars = Webinar::whereIn('id', $bestSaleWebinarsIds)
                ->where('status', Webinar::$active)
                ->where('private', false)
                ->with($webinarRelations)
                ->get();
        }

        if (in_array(HomeSection::$best_rates, $selectedSectionsName)) {
            $bestRateWebinars = Webinar::query()
                ->join('webinar_reviews', function ($join) {
                    $join->on("webinars.id", '=', "webinar_reviews.webinar_id");
                    $join->where('webinar_reviews.status', 'active');
                })
                ->select('webinars.*', DB::raw('avg(rates) as avg_rates'))
                ->where('webinars.private', false)
                ->where('webinars.status', 'active')
                ->whereNotNull('webinar_reviews.rates')
                ->groupBy("webinars.id")
                ->orderBy('avg_rates', 'desc')
                ->with($webinarRelations)
                ->limit(6)
                ->get();
        }

        // hasDiscountWebinars
        if (in_array(HomeSection::$discount_classes, $selectedSectionsName)) {
            $now = time();
            $hasDiscountWebinars = Webinar::where('status', Webinar::$active)
                ->where('private', false)
                ->where(function ($query) use ($now) {
                    $query->whereHas('specialOffers', function ($q) use ($now) {
                        $q->where('status', 'active')
                            ->where('from_date', '<', $now)
                            ->where('to_date', '>', $now);
                    })->orWhereHas('tickets', function ($q) use ($now) {
                        $q->where('start_date', '<', $now)
                            ->where('end_date', '>', $now);
                    });
                })
                ->with($webinarRelations)
                ->limit(6)
                ->get();
        }
        // .\ hasDiscountWebinars

        if (in_array(HomeSection::$free_classes, $selectedSectionsName)) {
            $freeWebinars = Webinar::where('status', Webinar::$active)
                ->where('private', false)
                ->where(function ($query) {
                    $query->whereNull('price')
                        ->orWhere('price', '0');
                })
                ->orderBy('updated_at', 'desc')
                ->with($webinarRelations)
                ->limit(6)
                ->get();
        }

        if (in_array(HomeSection::$store_products, $selectedSectionsName)) {
            $newProducts = Product::where('status', Product::$active)
                ->orderBy('updated_at', 'desc')
                ->with([
                    'translations',
                    'category.translations',
                    'discounts',
                    'productBadgeContent.badge.translations',
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'creator' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    },
                ])
                ->limit(6)
                ->get();
        }

        if (in_array(HomeSection::$trend_categories, $selectedSectionsName)) {
            $trendCategories = TrendCategory::with([
                'category' => function ($query) {
                    $query->with('translations')->withCount([
                        'webinars' => function ($query) {
                            $query->where('status', 'active');
                        }
                    ]);
                }
            ])->orderBy('created_at', 'desc')
                ->get();
        }

        if (in_array(HomeSection::$blog, $selectedSectionsName)) {
            $blog = Blog::where('status', 'publish')
                ->with(['translations', 'category.translations', 'author' => function ($query) {
                    $query->select('id', 'full_name');
                }])->orderBy('updated_at', 'desc')
                ->withCount('comments')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
        }

        if (in_array(HomeSection::$instructors, $selectedSectionsName)) {
            $instructors = User::where('role_name', Role::$teacher)
                ->select('id', 'full_name', 'avatar', 'bio')
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->where('ban', false)
                        ->orWhere(function ($query) {
                            $query->whereNotNull('ban_end_at')
                                ->where('ban_end_at', '<', time());
                        });
                })
                ->with([
                    'occupations' => function ($q) {
                        $q->with('category.translations');
                    }
                ])
                ->limit(8)
                ->get();
        }

        if (in_array(HomeSection::$organizations, $selectedSectionsName)) {
            $organizations = User::where('role_name', Role::$organization)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->where('ban', false)
                        ->orWhere(function ($query) {
                            $query->whereNotNull('ban_end_at')
                                ->where('ban_end_at', '<', time());
                        });
                })
                ->withCount('webinars')
                ->orderBy('webinars_count', 'desc')
                ->limit(6)
                ->get();
        }

        if (in_array(HomeSection::$testimonials, $selectedSectionsName)) {
            $testimonials = Testimonial::where('status', 'active')->with('translations')->get();
        }

        if (in_array(HomeSection::$subscribes, $selectedSectionsName)) {
            $subscribes = Subscribe::with([
                'translations',
                'specificationItems.category.translations',
                'specificationItems.instructor',
                'specificationItems.course',
                'specificationItems.bundle',
            ])->get();

    $user = auth()->user();
    $installmentPlans = new InstallmentPlans($user);

    foreach ($subscribes as $subscribe) {

        if (
            getInstallmentsSettings('status') &&
            (empty($user) || $user->enable_installments) &&
            $subscribe->price > 0
        ) {

            $installments = $installmentPlans->getPlans(
                'subscription_packages',
                $subscribe->id
            );

            $subscribe->has_installment =
                (!empty($installments) && count($installments));
        }
    }
}

        if (in_array(HomeSection::$find_instructors, $selectedSectionsName)) {
            $findInstructorSection = getFindInstructorsSettings();
        }

        if (in_array(HomeSection::$reward_program, $selectedSectionsName)) {
            $rewardProgramSection = getRewardProgramSettings();
        }


        if (in_array(HomeSection::$become_instructor, $selectedSectionsName)) {
            $becomeInstructorSection = getBecomeInstructorSectionSettings();
        }


        if (in_array(HomeSection::$forum_section, $selectedSectionsName)) {
            $forumSection = getForumSectionSettings();
        }

        $advertisingBanners = AdvertisingBanner::where('published', true)
            ->whereIn('position', ['home1', 'home2'])
            ->get();


        $siteGeneralSettings = getGeneralSettings();
        $heroSection = (!empty($siteGeneralSettings['hero_section2']) and $siteGeneralSettings['hero_section2'] == "1") ? "2" : "1";
        $heroSectionData = getHomeHeroSettings($heroSection);

        if (in_array(HomeSection::$video_or_image_section, $selectedSectionsName)) {
            $boxVideoOrImage = getHomeVideoOrImageBoxSettings();
        }

        $seoSettings = getSeoMetas('home');
        $pageTitle = !empty($seoSettings['title']) ? $seoSettings['title'] : trans('home.home_title');
        $pageDescription = !empty($seoSettings['description']) ? $seoSettings['description'] : trans('home.home_title');
        $pageRobot = getPageRobot('home');

        $statisticsSettings = getStatisticsSettings();

        $homeDefaultStatistics = null;
        $homeCustomStatistics = null;

        if (!empty($statisticsSettings['enable_statistics'])) {
            if (!empty($statisticsSettings['display_default_statistics'])) {
                $homeDefaultStatistics = $this->getHomeDefaultStatistics();
            } else {
                $homeCustomStatistics = HomePageStatistic::query()->orderBy('order', 'asc')->limit(4)->get();
            }
        }

        $data = [
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'pageRobot' => $pageRobot,
            'heroSection' => $heroSection,
            'heroSectionData' => $heroSectionData,
            'homeSections' => $homeSections,
            'featureWebinars' => $featureWebinars,
            'latestWebinars' => $latestWebinars ?? [],
            'latestBundles' => $latestBundles ?? [],
            'upcomingCourses' => $upcomingCourses ?? [],
            'bestSaleWebinars' => $bestSaleWebinars ?? [],
            'hasDiscountWebinars' => $hasDiscountWebinars ?? [],
            'bestRateWebinars' => $bestRateWebinars ?? [],
            'freeWebinars' => $freeWebinars ?? [],
            'newProducts' => $newProducts ?? [],
            'trendCategories' => $trendCategories ?? [],
            'instructors' => $instructors ?? [],
            'testimonials' => $testimonials ?? [],
            'subscribes' => $subscribes ?? [],
            'blog' => $blog ?? [],
            'organizations' => $organizations ?? [],
            'advertisingBanners1' => $advertisingBanners->where('position', 'home1'),
            'advertisingBanners2' => $advertisingBanners->where('position', 'home2'),
            'homeDefaultStatistics' => $homeDefaultStatistics,
            'homeCustomStatistics' => $homeCustomStatistics,
            'boxVideoOrImage' => $boxVideoOrImage ?? null,
            'findInstructorSection' => $findInstructorSection ?? null,
            'rewardProgramSection' => $rewardProgramSection ?? null,
            'becomeInstructorSection' => $becomeInstructorSection ?? null,
            'forumSection' => $forumSection ?? null,
        ];

        return view(getTemplate() . '.pages.home', $data);
    }

    private function getHomeDefaultStatistics()
    {
        $skillfulTeachersCount = User::where('role_name', Role::$teacher)
            ->where(function ($query) {
                $query->where('ban', false)
                    ->orWhere(function ($query) {
                        $query->whereNotNull('ban_end_at')
                            ->where('ban_end_at', '<', time());
                    });
            })
            ->where('status', 'active')
            ->count();

        $studentsCount = User::where('role_name', Role::$user)
            ->where(function ($query) {
                $query->where('ban', false)
                    ->orWhere(function ($query) {
                        $query->whereNotNull('ban_end_at')
                            ->where('ban_end_at', '<', time());
                    });
            })
            ->where('status', 'active')
            ->count();

        $liveClassCount = Webinar::where('type', 'webinar')
            ->where('status', 'active')
            ->count();

        $offlineCourseCount = Webinar::where('status', 'active')
            ->whereIn('type', ['course', 'text_lesson'])
            ->count();

        return [
            'skillfulTeachersCount' => $skillfulTeachersCount,
            'studentsCount' => $studentsCount,
            'liveClassCount' => $liveClassCount,
            'offlineCourseCount' => $offlineCourseCount,
        ];
    }
}
