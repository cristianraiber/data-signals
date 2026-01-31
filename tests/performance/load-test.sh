#!/bin/bash
#
# Load Testing Script for Data Signals
#
# Simulates 10,000 visits/minute (166 req/sec) to test tracking endpoint
#
# Requirements:
#   - siege (brew install siege) or
#   - Apache Bench (ab) - comes with Apache
#
# Usage:
#   ./load-test.sh [siege|ab] [url]
#

set -e

# Default configuration
TOOL="${1:-siege}"
BASE_URL="${2:-http://localhost/wp-json/data-signals/v1}"
TRACK_ENDPOINT="${BASE_URL}/track"
CONCURRENT=166  # 10,000/min = 166/sec
DURATION="1M"   # 1 minute
REQUESTS=10000  # Total requests

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
print_header() {
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}$1${NC}"
    echo -e "${GREEN}========================================${NC}"
}

print_info() {
    echo -e "${YELLOW}[INFO]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# Check if tool is installed
check_tool() {
    if ! command -v "$1" &> /dev/null; then
        print_error "$1 is not installed"
        echo ""
        echo "Install with:"
        if [ "$1" == "siege" ]; then
            echo "  macOS: brew install siege"
            echo "  Linux: sudo apt-get install siege"
        elif [ "$1" == "ab" ]; then
            echo "  macOS: comes with system (Apache)"
            echo "  Linux: sudo apt-get install apache2-utils"
        fi
        exit 1
    fi
}

# Generate test payload
generate_payload() {
    cat << EOF
{
  "url": "https://example.com/test-page?session=${RANDOM}",
  "referrer": "https://google.com",
  "page_id": 123,
  "utm_source": "test",
  "utm_medium": "load-test",
  "utm_campaign": "performance-${RANDOM}",
  "country": "US"
}
EOF
}

# Siege load test
run_siege() {
    print_header "Running Siege Load Test"
    print_info "Endpoint: $TRACK_ENDPOINT"
    print_info "Concurrent: $CONCURRENT users"
    print_info "Duration: $DURATION"
    print_info "Target: 10,000 requests/minute (166 req/sec)"
    echo ""

    # Create URLs file with POST data
    URLS_FILE="/tmp/siege-urls-$$.txt"
    for i in {1..1000}; do
        echo "$TRACK_ENDPOINT POST {\"url\":\"https://example.com/page-$i\",\"page_id\":$i,\"utm_campaign\":\"test-$i\"}" >> "$URLS_FILE"
    done

    print_info "Starting load test..."
    siege -c "$CONCURRENT" \
          -t "$DURATION" \
          -f "$URLS_FILE" \
          --content-type="application/json" \
          --no-parser \
          --benchmark \
          --internet

    rm -f "$URLS_FILE"
    print_success "Load test completed"
}

# Apache Bench load test
run_ab() {
    print_header "Running Apache Bench Load Test"
    print_info "Endpoint: $TRACK_ENDPOINT"
    print_info "Concurrent: $CONCURRENT requests"
    print_info "Total: $REQUESTS requests"
    print_info "Target: 10,000 requests/minute (166 req/sec)"
    echo ""

    # Create payload file
    PAYLOAD_FILE="/tmp/ab-payload-$$.json"
    generate_payload > "$PAYLOAD_FILE"

    print_info "Starting load test..."
    ab -n "$REQUESTS" \
       -c "$CONCURRENT" \
       -p "$PAYLOAD_FILE" \
       -T "application/json" \
       -g "/tmp/ab-results-$$.tsv" \
       "$TRACK_ENDPOINT"

    rm -f "$PAYLOAD_FILE"
    print_success "Load test completed"
    print_info "Results saved to: /tmp/ab-results-$$.tsv"
}

# Warm-up test
run_warmup() {
    print_header "Warming up server..."
    
    for i in {1..100}; do
        curl -s -X POST "$TRACK_ENDPOINT" \
             -H "Content-Type: application/json" \
             -d "$(generate_payload)" > /dev/null
    done
    
    print_success "Warm-up completed (100 requests)"
    sleep 2
}

# Health check
check_endpoint() {
    print_header "Health Check"
    print_info "Testing endpoint: $TRACK_ENDPOINT"
    
    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$TRACK_ENDPOINT" \
                    -H "Content-Type: application/json" \
                    -d "$(generate_payload)")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)
    
    if [ "$HTTP_CODE" -eq 200 ] || [ "$HTTP_CODE" -eq 201 ]; then
        print_success "Endpoint is healthy (HTTP $HTTP_CODE)"
        echo "Response: $BODY"
    else
        print_error "Endpoint returned HTTP $HTTP_CODE"
        echo "Response: $BODY"
        exit 1
    fi
    echo ""
}

# Performance analysis
analyze_performance() {
    print_header "Performance Analysis"
    
    # Run quick benchmark
    print_info "Running 1000 requests with 10 concurrent connections..."
    
    PAYLOAD_FILE="/tmp/perf-payload-$$.json"
    generate_payload > "$PAYLOAD_FILE"
    
    RESULTS=$(ab -n 1000 -c 10 -p "$PAYLOAD_FILE" -T "application/json" "$TRACK_ENDPOINT" 2>/dev/null)
    
    # Parse results
    REQUESTS_PER_SEC=$(echo "$RESULTS" | grep "Requests per second" | awk '{print $4}')
    TIME_PER_REQUEST=$(echo "$RESULTS" | grep "Time per request" | head -n1 | awk '{print $4}')
    PERCENTILE_50=$(echo "$RESULTS" | grep "50%" | awk '{print $2}')
    PERCENTILE_95=$(echo "$RESULTS" | grep "95%" | awk '{print $2}')
    PERCENTILE_99=$(echo "$RESULTS" | grep "99%" | awk '{print $2}')
    
    echo ""
    echo "Results:"
    echo "  Requests/sec:    $REQUESTS_PER_SEC"
    echo "  Avg time:        ${TIME_PER_REQUEST}ms"
    echo "  50th percentile: ${PERCENTILE_50}ms"
    echo "  95th percentile: ${PERCENTILE_95}ms"
    echo "  99th percentile: ${PERCENTILE_99}ms"
    echo ""
    
    # Evaluate against targets
    if (( $(echo "$TIME_PER_REQUEST < 50" | bc -l) )); then
        print_success "Response time meets target (< 50ms)"
    else
        print_error "Response time exceeds target (${TIME_PER_REQUEST}ms > 50ms)"
    fi
    
    if (( $(echo "$REQUESTS_PER_SEC >= 166" | bc -l) )); then
        print_success "Throughput meets target (>= 166 req/sec)"
    else
        print_error "Throughput below target (${REQUESTS_PER_SEC} < 166 req/sec)"
    fi
    
    rm -f "$PAYLOAD_FILE"
}

# Main execution
main() {
    print_header "Data Signals Load Testing"
    echo ""
    
    # Check endpoint health
    check_endpoint
    
    # Run warm-up
    run_warmup
    
    # Performance analysis
    analyze_performance
    
    # Run full load test
    if [ "$TOOL" == "siege" ]; then
        check_tool siege
        run_siege
    elif [ "$TOOL" == "ab" ]; then
        check_tool ab
        run_ab
    else
        print_error "Unknown tool: $TOOL"
        echo "Usage: $0 [siege|ab] [url]"
        exit 1
    fi
    
    echo ""
    print_success "All tests completed!"
    echo ""
    echo "Next steps:"
    echo "  1. Review performance metrics"
    echo "  2. Check server logs for errors"
    echo "  3. Verify database performance"
    echo "  4. Test with Redis caching enabled"
}

# Run main function
main
