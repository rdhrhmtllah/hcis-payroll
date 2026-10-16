@extends('layouts.master')

@section('title', $title)

@section('content')
<section class="section">
    <div class="row" id="basic-table">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{$title}}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <form action="/assestmentGlobalReview" method="POST" id="assessmentForm">
                            @csrf
                            <div class="assessment-container">
                                <div class="row mb-2">
                                 
                                    <div class="col-md-4">
                                        <label for="">Mulai</label>
                                        <input type="date" name="tanggal_mulai" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="">Sampai</label>
                                        <input type="date" name="tanggal_akhir" class="form-control">
                                    </div>
                                </div>
                                @foreach ($data as $item)
                                    <div class="assessment-section mb-4">
                                        <div class="section-header d-flex justify-content-between align-items-center bg-light p-3 rounded"
                                            data-bs-toggle="collapse" data-bs-target="#individualSection">
                                            <h5 class="mb-0">{{ $item['category'] }}</h5>
                                            <i class="bi bi-chevron-down transition-transform"></i>
                                        </div>
                                        <div id="individualSection" class="collapse show">
                                            <div class="table-container mt-3">
                                                <div class="table-wrapper">
                                                    <table class="table table-bordered assessment-table">
                                                        <thead>
                                                            <tr>
                                                                <th class="fixed-column">Nama User</th>
                                                                @foreach($item['sub_category'] as $criteria)
                                                                <th class="scrollable-column">
                                                                    {{ $criteria['name'] }}
                                                                    <input type="hidden" name="criteria_ids[]" value="{{ $criteria['id'] }}">
                                                                </th>
                                                                @endforeach
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($user as $userreach)
                                                            <tr>
                                                                <td class="fixed-column">
                                                                    <input type="hidden" name="user_ids[]" value="{{ $userreach->Id_Users }}">
                                                                    <span class="user-name">{{ $userreach->Username }}</span>
                                                                </td>
                                                                @foreach($item['sub_category'] as  $criteria)
                                                                <td class="scrollable-column">
                                                                    <div class="rating" data-user="{{ $userreach->Id_Users }}"
                                                                        data-criteria="{{ $criteria['id'] }}">
                                                                        @for($i = 5; $i >= 1; $i--)
                                                                        <input type="radio"
                                                                            name="ratings[{{ $userreach->Id_Users }}][{{ $criteria['id'] }}]"
                                                                            value="{{ $i }}"
                                                                            id="rating_{{ $userreach->Id_Users }}_{{ $criteria['id'] }}_{{ $i }}"
                                                                            class="rating-input" required>
                                                                        <label for="rating_{{ $userreach->Id_Users }}_{{ $criteria['id'] }}_{{ $i }}"
                                                                            title="{{ $i }} stars">★</label>
                                                                        @endfor
                                                                    </div>
                                                                </td>
                                                                @endforeach
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <span class="text-muted" id="completionStatus">0% completed</span>
                                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                        Submit Penilaian
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    gap: 0.25rem;
}

.rating input {
    display: none;
}

.rating label {
    cursor: pointer;
    font-size: 1.5rem;
    color: #ddd;
    transition: all 0.2s ease;
}

.rating label:hover,
.rating label:hover ~ label,
.rating input:checked ~ label {
    color: #ffd700;
    transform: scale(1.1);
    text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
}

.card {
    border: none;
    border-radius: 1.25rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    background: linear-gradient(to bottom, var(--hcis-white), #fafbfc);
    transition: all 0.3s ease;
}

.card-header {
    background: transparent;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1.5rem 2rem;
}

.card-title {
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.5rem;
    margin: 0;
}

.card-body {
    padding: 2rem;
}

/* Section Header */
.section-header {
    background: linear-gradient(to right, #f8f9fa, #f1f3f5);
    border: 1px solid var(--hcis-legacy-bootstrap-gray);
    border-radius: 0.75rem;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.section-header:hover {
    background: linear-gradient(to right, #f1f3f5, var(--hcis-legacy-bootstrap-gray));
    transform: translateY(-1px);
}

.section-header h5 {
    color: #345;
    font-weight: 600;
}

/* Table Styling */
.table-container {
    position: relative;
    overflow: hidden;
    border-radius: 1rem;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
    background: #fff;
}

.table-wrapper {
    overflow-x: auto;
    margin-left: 200px;
    scrollbar-width: thin;
    scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
}

.table {
    margin-bottom: 0;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #345;
    padding: 1rem;
    white-space: nowrap;
    border-bottom: 2px solid var(--hcis-legacy-bootstrap-gray);
}

.table td {
    padding: 1rem;
    vertical-align: middle;
    border-color: var(--hcis-legacy-bootstrap-gray);
}

.fixed-column {
    position: absolute;
    width: 200px;
    left: 0;
    background: #fff;
    border-right: 2px solid var(--hcis-legacy-bootstrap-gray);
    z-index: 1;
    padding: 1rem;
    font-weight: 500;
    color: #345;
}

@media (max-width: 768px) {
    .table-wrapper {
        margin-left: 150px;
    }
    
    .fixed-column {
        width: 150px;
    }
    
    .rating label {
        font-size: 1.25rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('assessmentForm');
    const submitBtn = document.getElementById('submitBtn');
    const completionStatus = document.getElementById('completionStatus');
    const totalRatings = document.querySelectorAll('.rating').length;
    let completedRatings = 0;

    // Handle section collapse
    const sectionHeader = document.querySelector('.section-header');
    const icon = sectionHeader.querySelector('.bi-chevron-down');
    
    sectionHeader.addEventListener('click', function() {
        icon.style.transform = icon.style.transform === 'rotate(180deg)' 
            ? 'rotate(0deg)' 
            : 'rotate(180deg)';
    });

    // Sync scroll of fixed column
    const tableWrapper = document.querySelector('.table-wrapper');
    tableWrapper.addEventListener('scroll', function() {
        const fixedCells = document.querySelectorAll('.fixed-column');
        const scrollTop = this.scrollTop;
        
        fixedCells.forEach(cell => {
            cell.style.transform = `translateY(-${scrollTop}px)`;
        });
    });

    // Track completion status
    document.querySelectorAll('.rating-input').forEach(input => {
        input.addEventListener('change', function() {
            const ratingGroup = this.closest('.rating');
            if (!ratingGroup.dataset.completed) {
                ratingGroup.dataset.completed = 'true';
                completedRatings++;
                
                const percentage = Math.round((completedRatings / totalRatings) * 100);
                completionStatus.textContent = `${percentage}% completed`;
                
                submitBtn.disabled = completedRatings !== totalRatings;
            }
        });
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (completedRatings === totalRatings) {
            // Show confirmation dialog
            if (confirm('Are you sure you want to submit this assessment?')) {
                this.submit();
            }
        } else {
            alert('Please complete all ratings before submitting.');
        }
    });
});
</script>
@endsection