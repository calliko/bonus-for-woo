jQuery(function($) {
    // Check if bfwAnalyticsData is available
    if (typeof bfwAnalyticsData === 'undefined') {
        console.error('bfwAnalyticsData is not defined');
        return;
    }

    const bfwAnalytics = {
        nonce: bfwAnalyticsData.nonce,

        // Get points distribution
        getDistribution: function() {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bfw_analytics_distribution',
                    nonce: this.nonce
                }
            });
        },

        // Get points trend
        getTrend: function(days = 30) {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bfw_analytics_trend',
                    nonce: this.nonce,
                    days: days
                }
            });
        },

        // Get overall stats
        getOverallStats: function() {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bfw_analytics_overall',
                    nonce: this.nonce
                }
            });
        },

        // Get top users
        getTopUsers: function(limit = 10) {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bfw_analytics_top_users',
                    nonce: this.nonce,
                    limit: limit
                }
            });
        },

        // Get status distribution
        getStatusDistribution: function() {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bfw_analytics_status',
                    nonce: this.nonce
                }
            });
        },

        // Get referral stats
        getReferralStats: function() {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bfw_analytics_referral',
                    nonce: this.nonce
                }
            });
        },

        // Get coupon stats
        getCouponStats: function() {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bfw_analytics_coupon',
                    nonce: this.nonce
                }
            });
        },

        // Export to CSV
        exportToCSV: function(type) {
            return $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bfw_analytics_export',
                    nonce: this.nonce,
                    type: type
                }
            });
        }
    };

    // Make available globally
    window.bfwAnalytics = bfwAnalytics;
    console.log('bfwAnalytics initialized');
});
