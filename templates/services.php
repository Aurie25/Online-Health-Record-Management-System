<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services — ApexCare Hospital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/services.css">
</head>
<body>

<!-- ══════════════════════════════
     NAVBAR
══════════════════════════════ -->
<nav class="navbar">
    <div class="nav-container">
        <a href="home.php" class="logo">
            <i class="fas fa-plus-circle"></i>
            ApexCare
        </a>
        <a href="home.php" class="nav-back">
            <i class="fas fa-arrow-left"></i>
            Back to Home
        </a>
    </div>
</nav>

<!-- ══════════════════════════════
     HERO
══════════════════════════════ -->
<section class="services-hero">
    <div class="hero-ring hero-ring-1"></div>
    <div class="hero-ring hero-ring-2"></div>
    <div class="hero-ring hero-ring-3"></div>

    <div class="container">
        <div class="hero-content">
            <div class="hero-eyebrow">ApexCare Hospital &bull; Services</div>
            <h1 class="hero-title">
                World-Class Care,<br>
                <em>Every Step Forward</em>
            </h1>
            <p class="hero-description">
                From preventive screenings to complex surgical procedures, our multidisciplinary teams deliver evidence-based care tailored to every patient's unique journey.
            </p>

            <div class="hero-strip">
                <div class="strip-item">
                    <div class="strip-num">20+</div>
                    <div class="strip-label">Departments</div>
                </div>
                <div class="strip-item">
                    <div class="strip-num">150+</div>
                    <div class="strip-label">Specialists</div>
                </div>
                <div class="strip-item">
                    <div class="strip-num">24/7</div>
                    <div class="strip-label">Emergency</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════
     FILTER NAV
══════════════════════════════ -->
<div class="filter-section">
    <div class="container">
        <div class="filter-inner">
            <button class="filter-btn active" data-filter="all" onclick="filterServices('all', this)">
                <i class="fas fa-grid-2"></i> All Services
            </button>
            <button class="filter-btn" data-filter="diagnostic" onclick="filterServices('diagnostic', this)">
                <i class="fas fa-microscope"></i> Diagnostics
            </button>
            <button class="filter-btn" data-filter="surgical" onclick="filterServices('surgical', this)">
                <i class="fas fa-scalpel"></i> Surgical
            </button>
            <button class="filter-btn" data-filter="maternal" onclick="filterServices('maternal', this)">
                <i class="fas fa-baby"></i> Maternal & Child
            </button>
            <button class="filter-btn" data-filter="emergency" onclick="filterServices('emergency', this)">
                <i class="fas fa-truck-medical"></i> Emergency
            </button>
            <button class="filter-btn" data-filter="specialist" onclick="filterServices('specialist', this)">
                <i class="fas fa-user-doctor"></i> Specialist Clinics
            </button>
            <button class="filter-btn" data-filter="wellness" onclick="filterServices('wellness', this)">
                <i class="fas fa-heart-pulse"></i> Wellness
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════
     FEATURED SERVICES
══════════════════════════════ -->
<section class="featured-section">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-subtitle">Flagship Services</div>
            <h2 class="section-title">What We're Known For</h2>
            <p class="section-lead">Our signature departments combine cutting-edge technology with compassionate specialists to deliver outcomes that matter.</p>
            <div class="section-divider"></div>
        </div>

        <div class="featured-grid">

            <!-- Wide card: Emergency -->
            <div class="featured-card wide reveal">
                <div class="card-image">
                    <img src="../ambulancestaff.jpeg" alt="ER Staff">
                   
                </div>
                <div class="card-body">
                    <div class="card-icon-sm"><i class="fas fa-truck-medical"></i></div>
                    <h3>Emergency & Trauma Care</h3>
                    <p>Our Level-1 Emergency Centre is staffed around the clock with senior consultants, trauma surgeons, and resuscitation teams ready to respond within minutes. Equipped with state-of-the-art resuscitation bays and direct links to our ICU and operating theatres.</p>
                    <ul class="card-features">
                        <li><i class="fas fa-check-circle"></i> Dedicated trauma bays with advanced monitoring</li>
                        <li><i class="fas fa-check-circle"></i> Paediatric and adult emergency streams</li>
                        <li><i class="fas fa-check-circle"></i> Direct fast-track to ICU & Theatre</li>
                        <li><i class="fas fa-check-circle"></i> On-site blood bank & pharmacy</li>
                    </ul>
                    <a href="#" class="card-cta">Learn more <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <!-- Card: Cardiology -->
            <div class="featured-card reveal reveal-delay-1">
                <div class="card-image">
                 <img src="../surgeon.jpeg" alt="Cardiology">
                </div>
                <div class="card-body">
                    <div class="card-icon-sm"><i class="fas fa-heart-pulse"></i></div>
                    <h3>Cardiology & Heart Centre</h3>
                    <p>Advanced diagnostics, interventional procedures, and long-term cardiac management by board-certified cardiologists using the latest echo, stress-testing, and catheter lab technology.</p>
                    <ul class="card-features">
                        <li><i class="fas fa-check-circle"></i> Echocardiography & Holter monitoring</li>
                        <li><i class="fas fa-check-circle"></i> Coronary angioplasty & stenting</li>
                        <li><i class="fas fa-check-circle"></i> Cardiac rehabilitation programmes</li>
                    </ul>
                    <a href="#" class="card-cta">Learn more <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <!-- Card: Maternity -->
            <div class="featured-card reveal reveal-delay-2">
                <div class="card-image">
                    <img src="../infant.jpeg" alt="Maternity care">
                </div>
                <div class="card-body">
                    <div class="card-icon-sm"><i class="fas fa-baby"></i></div>
                    <h3>Maternity & Neonatal Unit</h3>
                    <p>A warm, family-centred maternity wing supporting mothers from antenatal care through delivery and postnatal recovery, with a fully equipped neonatal intensive care unit for premature and high-risk newborns.</p>
                    <ul class="card-features">
                        <li><i class="fas fa-check-circle"></i> Antenatal & postnatal clinics</li>
                        <li><i class="fas fa-check-circle"></i> Private birthing suites</li>
                        <li><i class="fas fa-check-circle"></i> Level-3 NICU on site</li>
                    </ul>
                    <a href="#" class="card-cta">Learn more <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════
     ALL SERVICES GRID
══════════════════════════════ -->
<section class="all-services-section">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-subtitle">Full Directory</div>
            <h2 class="section-title">All Hospital Services</h2>
            <p class="section-lead">Browse our complete range of clinical departments and outpatient services. Use the filter above to find exactly what you need.</p>
            <div class="section-divider"></div>
        </div>

        <div class="services-masonry" id="servicesGrid">

            <!-- Diagnostics -->
            <div class="service-tile reveal" data-category="diagnostic">
                <div class="tile-icon"><i class="fas fa-microscope"></i></div>
                <div class="tile-category">Diagnostics</div>
                <h3>Laboratory & Pathology</h3>
                <p>Full-spectrum blood, urine, microbiology, and histopathology testing with same-day results for most routine panels.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Blood Tests</span>
                    <span class="tile-tag">Pathology</span>
                    <span class="tile-tag">Microbiology</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-1" data-category="diagnostic">
                <div class="tile-icon"><i class="fas fa-x-ray"></i></div>
                <div class="tile-category">Diagnostics</div>
                <h3>Radiology & Imaging</h3>
                <p>Digital X-ray, ultrasound, CT, MRI, and fluoroscopy services with radiologist-reported results for accurate diagnosis.</p>
                <div class="tile-tags">
                    <span class="tile-tag">X-Ray</span>
                    <span class="tile-tag">MRI</span>
                    <span class="tile-tag">CT Scan</span>
                    <span class="tile-tag">Ultrasound</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-2" data-category="diagnostic">
                <div class="tile-icon"><i class="fas fa-heart-circle-check"></i></div>
                <div class="tile-category">Diagnostics</div>
                <h3>Cardiac Diagnostics</h3>
                <p>ECG, echocardiography, stress testing, and ambulatory Holter monitoring for comprehensive heart health assessment.</p>
                <div class="tile-tags">
                    <span class="tile-tag">ECG</span>
                    <span class="tile-tag">Echo</span>
                    <span class="tile-tag">Holter</span>
                </div>
            </div>

            <!-- Surgical -->
            <div class="service-tile reveal" data-category="surgical">
                <div class="tile-icon"><i class="fas fa-scalpel"></i></div>
                <div class="tile-category">Surgical</div>
                <h3>General Surgery</h3>
                <p>Elective and emergency abdominal, hernia, appendix, and soft-tissue surgeries performed by experienced consultant surgeons.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Laparoscopic</span>
                    <span class="tile-tag">Open Surgery</span>
                    <span class="tile-tag">Hernia</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-1" data-category="surgical">
                <div class="tile-icon"><i class="fas fa-bone"></i></div>
                <div class="tile-category">Surgical</div>
                <h3>Orthopaedic Surgery</h3>
                <p>Joint replacements, fracture management, sports injury repairs, and spinal procedures using minimally invasive techniques.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Hip & Knee</span>
                    <span class="tile-tag">Fractures</span>
                    <span class="tile-tag">Spine</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-2" data-category="surgical">
                <div class="tile-icon"><i class="fas fa-eye"></i></div>
                <div class="tile-category">Surgical</div>
                <h3>Ophthalmology & Eye Surgery</h3>
                <p>Cataract extraction, glaucoma procedures, retinal treatments, and routine refractive surgical options.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Cataracts</span>
                    <span class="tile-tag">Glaucoma</span>
                    <span class="tile-tag">Retina</span>
                </div>
            </div>

            <!-- Maternal -->
            <div class="service-tile reveal" data-category="maternal">
                <div class="tile-icon"><i class="fas fa-person-pregnant"></i></div>
                <div class="tile-category">Maternal & Child</div>
                <h3>Antenatal Care</h3>
                <p>Structured antenatal clinics from 8 weeks to term, with growth scans, anomaly screening, and high-risk obstetric follow-up.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Scans</span>
                    <span class="tile-tag">High-Risk OB</span>
                    <span class="tile-tag">Screening</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-1" data-category="maternal">
                <div class="tile-icon"><i class="fas fa-baby-carriage"></i></div>
                <div class="tile-category">Maternal & Child</div>
                <h3>Paediatrics & Child Health</h3>
                <p>From newborn checks through adolescent medicine, our paediatric team covers vaccinations, development assessments, and acute illness management.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Newborn Care</span>
                    <span class="tile-tag">Vaccinations</span>
                    <span class="tile-tag">Development</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-2" data-category="maternal">
                <div class="tile-icon"><i class="fas fa-ribbon"></i></div>
                <div class="tile-category">Maternal & Child</div>
                <h3>Gynaecology Clinic</h3>
                <p>Comprehensive women's health including fibroid management, menstrual disorders, cervical screening, and minimally invasive gynaecological surgery.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Cervical Screening</span>
                    <span class="tile-tag">Fibroids</span>
                    <span class="tile-tag">Laparoscopy</span>
                </div>
            </div>

            <!-- Emergency -->
            <div class="service-tile reveal" data-category="emergency">
                <div class="tile-icon"><i class="fas fa-bed-pulse"></i></div>
                <div class="tile-category">Emergency</div>
                <h3>Intensive Care Unit (ICU)</h3>
                <p>8-bed adult ICU providing continuous multi-organ monitoring, ventilatory support, and specialist critical care for complex and post-operative patients.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Ventilation</span>
                    <span class="tile-tag">Multi-Organ</span>
                    <span class="tile-tag">Post-Op</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-1" data-category="emergency">
                <div class="tile-icon"><i class="fas fa-ambulance"></i></div>
                <div class="tile-category">Emergency</div>
                <h3>Ambulance & Patient Transfer</h3>
                <p>Advanced Life Support ambulances available for inter-facility transfers and emergency medical response, equipped with cardiac and airway management tools.</p>
                <div class="tile-tags">
                    <span class="tile-tag">ALS</span>
                    <span class="tile-tag">Transfer</span>
                    <span class="tile-tag">Rapid Response</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-2" data-category="emergency">
                <div class="tile-icon"><i class="fas fa-fire-flame-curved"></i></div>
                <div class="tile-category">Emergency</div>
                <h3>Burns & Wound Management</h3>
                <p>Dedicated burns unit managing partial and full-thickness burns, complex wound debridement, and skin graft procedures with specialist nursing care.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Burns Unit</span>
                    <span class="tile-tag">Grafting</span>
                    <span class="tile-tag">Wound Care</span>
                </div>
            </div>

            <!-- Specialist Clinics -->
            <div class="service-tile reveal" data-category="specialist">
                <div class="tile-icon"><i class="fas fa-brain"></i></div>
                <div class="tile-category">Specialist Clinic</div>
                <h3>Neurology & Neurosurgery</h3>
                <p>Diagnosis and management of stroke, epilepsy, Parkinson's disease, brain tumours, and spinal cord conditions by consultant neurologists and neurosurgeons.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Stroke</span>
                    <span class="tile-tag">Epilepsy</span>
                    <span class="tile-tag">Neurosurgery</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-1" data-category="specialist">
                <div class="tile-icon"><i class="fas fa-lungs"></i></div>
                <div class="tile-category">Specialist Clinic</div>
                <h3>Pulmonology & Chest Clinic</h3>
                <p>Asthma, COPD, sleep apnoea, and lung infection management with spirometry, bronchoscopy, and sleep study facilities on site.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Asthma</span>
                    <span class="tile-tag">COPD</span>
                    <span class="tile-tag">Sleep Studies</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-2" data-category="specialist">
                <div class="tile-icon"><i class="fas fa-syringe"></i></div>
                <div class="tile-category">Specialist Clinic</div>
                <h3>Endocrinology & Diabetes</h3>
                <p>Holistic management of diabetes mellitus, thyroid disorders, adrenal and pituitary conditions including structured patient education programmes.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Diabetes</span>
                    <span class="tile-tag">Thyroid</span>
                    <span class="tile-tag">Hormones</span>
                </div>
            </div>

            <div class="service-tile reveal" data-category="specialist">
                <div class="tile-icon"><i class="fas fa-tooth"></i></div>
                <div class="tile-category">Specialist Clinic</div>
                <h3>Dental & Maxillofacial</h3>
                <p>Comprehensive dental care from routine extractions and fillings to implants, orthodontics, and complex jaw and facial surgeries.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Implants</span>
                    <span class="tile-tag">Orthodontics</span>
                    <span class="tile-tag">Oral Surgery</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-1" data-category="specialist">
                <div class="tile-icon"><i class="fas fa-kidneys"></i></div>
                <div class="tile-category">Specialist Clinic</div>
                <h3>Nephrology & Dialysis</h3>
                <p>Chronic kidney disease management, renal biopsy, and a 10-station haemodialysis unit offering scheduled and emergency dialysis sessions.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Dialysis</span>
                    <span class="tile-tag">CKD</span>
                    <span class="tile-tag">Renal Biopsy</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-2" data-category="specialist">
                <div class="tile-icon"><i class="fas fa-person-dots-from-line"></i></div>
                <div class="tile-category">Specialist Clinic</div>
                <h3>Oncology & Cancer Care</h3>
                <p>Multidisciplinary cancer clinic offering chemotherapy infusion, palliative care, tumour board reviews, and survivor support programmes.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Chemotherapy</span>
                    <span class="tile-tag">Palliative</span>
                    <span class="tile-tag">MDT</span>
                </div>
            </div>

            <!-- Wellness -->
            <div class="service-tile reveal" data-category="wellness">
                <div class="tile-icon"><i class="fas fa-spa"></i></div>
                <div class="tile-category">Wellness</div>
                <h3>Physiotherapy & Rehabilitation</h3>
                <p>Post-surgical and injury rehabilitation, neurorehabilitation, and sports physiotherapy by certified physiotherapists with gym and hydrotherapy facilities.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Post-Op</span>
                    <span class="tile-tag">Sports</span>
                    <span class="tile-tag">Neuro-Rehab</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-1" data-category="wellness">
                <div class="tile-icon"><i class="fas fa-apple-whole"></i></div>
                <div class="tile-category">Wellness</div>
                <h3>Nutrition & Dietetics</h3>
                <p>Personalised dietary counselling for weight management, chronic disease nutrition, eating disorders, and sports performance optimisation.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Weight Mgmt</span>
                    <span class="tile-tag">Clinical Nutrition</span>
                    <span class="tile-tag">Sports Diet</span>
                </div>
            </div>

            <div class="service-tile reveal reveal-delay-2" data-category="wellness">
                <div class="tile-icon"><i class="fas fa-brain"></i></div>
                <div class="tile-category">Wellness</div>
                <h3>Mental Health & Counselling</h3>
                <p>Outpatient psychiatry, clinical psychology, and structured counselling for anxiety, depression, trauma, addiction, and workplace stress management.</p>
                <div class="tile-tags">
                    <span class="tile-tag">Psychiatry</span>
                    <span class="tile-tag">Psychology</span>
                    <span class="tile-tag">CBT</span>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════
     SPECIALTIES DARK BAND
══════════════════════════════ -->
<section class="specialties-section">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-subtitle" style="color:var(--teal-light);">
                <span style="display:inline-flex;align-items:center;gap:10px;">
                    <span style="display:inline-block;width:22px;height:1.5px;background:var(--teal-light);opacity:0.6;"></span>
                    Clinical Departments
                    <span style="display:inline-block;width:22px;height:1.5px;background:var(--teal-light);opacity:0.6;"></span>
                </span>
            </div>
            <h2 class="section-title" style="color:var(--white);">Our Medical Specialties</h2>
            <p class="section-lead" style="color:rgba(255,255,255,0.55);">Each department is led by senior consultants and supported by multidisciplinary teams committed to exceptional patient outcomes.</p>
            <div class="section-divider"></div>
        </div>

        <div class="specialties-grid">
            <div class="specialty-card reveal">
                <div class="specialty-icon"><i class="fas fa-heart-pulse"></i></div>
                <h4>Cardiology</h4>
                <p>Advanced heart care, interventional procedures and cardiac rehabilitation.</p>
                <span class="specialty-count"><i class="fas fa-user-doctor"></i> 8 Consultants</span>
            </div>
            <div class="specialty-card reveal reveal-delay-1">
                <div class="specialty-icon"><i class="fas fa-brain"></i></div>
                <h4>Neurosciences</h4>
                <p>Neurology, neurosurgery and neurophysiology under one roof.</p>
                <span class="specialty-count"><i class="fas fa-user-doctor"></i> 6 Consultants</span>
            </div>
            <div class="specialty-card reveal reveal-delay-2">
                <div class="specialty-icon"><i class="fas fa-bone"></i></div>
                <h4>Orthopaedics</h4>
                <p>Joints, spine, trauma and sports injuries with minimally invasive options.</p>
                <span class="specialty-count"><i class="fas fa-user-doctor"></i> 7 Consultants</span>
            </div>
            <div class="specialty-card reveal reveal-delay-3">
                <div class="specialty-icon"><i class="fas fa-baby"></i></div>
                <h4>Paediatrics</h4>
                <p>Child health from newborn care to adolescent medicine.</p>
                <span class="specialty-count"><i class="fas fa-user-doctor"></i> 10 Consultants</span>
            </div>
            <div class="specialty-card reveal">
                <div class="specialty-icon"><i class="fas fa-microscope"></i></div>
                <h4>Oncology</h4>
                <p>Multi-disciplinary cancer care with chemo, palliative and support services.</p>
                <span class="specialty-count"><i class="fas fa-user-doctor"></i> 5 Consultants</span>
            </div>
            <div class="specialty-card reveal reveal-delay-1">
                <div class="specialty-icon"><i class="fas fa-lungs"></i></div>
                <h4>Pulmonology</h4>
                <p>Respiratory medicine, bronchoscopy and sleep disorder management.</p>
                <span class="specialty-count"><i class="fas fa-user-doctor"></i> 4 Consultants</span>
            </div>
            <div class="specialty-card reveal reveal-delay-2">
                <div class="specialty-icon"><i class="fas fa-kidneys"></i></div>
                <h4>Nephrology</h4>
                <p>Kidney disease, dialysis and renal transplant coordination.</p>
                <span class="specialty-count"><i class="fas fa-user-doctor"></i> 4 Consultants</span>
            </div>
            <div class="specialty-card reveal reveal-delay-3">
                <div class="specialty-icon"><i class="fas fa-syringe"></i></div>
                <h4>Endocrinology</h4>
                <p>Diabetes, thyroid and hormone disorder management and education.</p>
                <span class="specialty-count"><i class="fas fa-user-doctor"></i> 3 Consultants</span>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════
     HOW IT WORKS
══════════════════════════════ -->
<section class="process-section">
    <div class="container">
        <div class="section-header reveal">
            <div class="section-subtitle">Patient Journey</div>
            <h2 class="section-title">Getting Care is Simple</h2>
            <p class="section-lead">We've designed our patient pathway to be straightforward, transparent, and centred on you.</p>
            <div class="section-divider"></div>
        </div>

        <div class="process-steps">
            <div class="process-step reveal">
                <div class="step-bubble">1</div>
                <h4>Register or Log In</h4>
                <p>Create your patient account on the portal or visit our reception to register in person.</p>
            </div>
            <div class="process-step reveal reveal-delay-1">
                <div class="step-bubble">2</div>
                <h4>Book an Appointment</h4>
                <p>Choose your department, select an available time slot, and confirm your booking instantly.</p>
            </div>
            <div class="process-step reveal reveal-delay-2">
                <div class="step-bubble">3</div>
                <h4>Attend Your Visit</h4>
                <p>Meet your consultant, undergo any required tests, and receive a personalised care plan.</p>
            </div>
            <div class="process-step reveal reveal-delay-3">
                <div class="step-bubble">4</div>
                <h4>Access Your Records</h4>
                <p>View your diagnoses, prescriptions, and follow-up notes securely through the patient portal.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════
     CTA
══════════════════════════════ -->
<section class="cta-section">
    <div class="cta-ring-1"></div>
    <div class="cta-ring-2"></div>
    <div class="container">
        <div class="cta-content reveal">
            <div class="cta-eyebrow">Start Today</div>
            <h2>Ready to Experience <em>Better Care?</em></h2>
            <p>Join thousands of patients who trust ApexCare for their health needs. Book your first appointment online or walk in to our reception — we're here for you.</p>
            <div class="cta-buttons">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-calendar-check"></i>
                    Book an Appointment
                </a>
                <a href="home.php#contact" class="btn btn-outline">
                    <i class="fas fa-phone"></i>
                    Contact Us
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════
     FOOTER
══════════════════════════════ -->
<footer class="site-footer">
    <p>
        &copy; <?php echo date('Y'); ?> ApexCare Hospital. All rights reserved.
        &nbsp;&bull;&nbsp;
        <a href="home.php">Home</a>
        &nbsp;&bull;&nbsp;
        <a href="login.php">Patient Portal</a>
    </p>
</footer>

<script>
    /* ── Filter logic ──────────────────────────── */
    function filterServices(category, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.querySelectorAll('.service-tile').forEach(tile => {
            if (category === 'all' || tile.dataset.category === category) {
                tile.removeAttribute('data-hidden');
                tile.style.animation = 'none';
                tile.offsetHeight; // force reflow
                tile.style.animation = '';
            } else {
                tile.setAttribute('data-hidden', 'true');
            }
        });

        document.getElementById('servicesGrid').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /* ── Scroll reveal ─────────────────────────── */
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
</script>

</body>
</html>