<?php 
defined('ABSPATH') or die("You can't access this file directly.");


add_filter('qc_wpbotpro_floating_openai_filter_for_style', 'qc_wpbotpro_floating_help_openai_filter_for_style', 10, 2);
if ( ! function_exists( 'qc_wpbotpro_floating_help_openai_filter_for_style' ) ) {
function qc_wpbotpro_floating_help_openai_filter_for_style($qcld_writing_style, $qcld_language){
    
        if ( $qcld_writing_style == "descr" ) {
            
            if ( $qcld_language == "en" ) {
                $writing_style = "descriptive";
            } else {
                
                if ( $qcld_language == "de" ) {
                    $writing_style = "beschreibend";
                } else {
                    
                    if ( $qcld_language == "fr" ) {
                        $writing_style = "descriptif";
                    } else {
                        
                        if ( $qcld_language == "es" ) {
                            $writing_style = "descriptivo";
                        } else {
                            
                            if ( $qcld_language == "it" ) {
                                $writing_style = "descrittivo";
                            } else {
                                
                                if ( $qcld_language == "pt" ) {
                                    $writing_style = "descritivo";
                                } else {
                                    
                                    if ( $qcld_language == "nl" ) {
                                        $writing_style = "beschrijvend";
                                    } else {
                                        
                                        if ( $qcld_language == "ru" ) {
                                            $writing_style = "описательный";
                                        } else {
                                            
                                            if ( $qcld_language == "ja" ) {
                                                $writing_style = "記述的";
                                            } else {
                                                
                                                if ( $qcld_language == "ko" ) {
                                                    $writing_style = "기술적인";
                                                } else {
                                                    
                                                    if ( $qcld_language == "zh" ) {
                                                        $writing_style = "描述性";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "ko" ) {
                                                            $writing_style = "기술적인";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "id" ) {
                                                                $writing_style = "deskriptif";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "tr" ) {
                                                                    $writing_style = "tanımlayıcı";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "hi" ) {
                                                                        $writing_style = "वर्णनात्मक";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "pl" ) {
                                                                            $writing_style = "opisowy";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "uk" ) {
                                                                                $writing_style = "описовий";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "ar" ) {
                                                                                    $writing_style = "وصفي";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ro" ) {
                                                                                        $writing_style = "descriptiv";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "hu" ) {
                                                                                            $writing_style = "leírásos";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "cs" ) {
                                                                                                $writing_style = "popisný";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "el" ) {
                                                                                                    $writing_style = "περιγραφικός";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "bg" ) {
                                                                                                        $writing_style = "описателен";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "sv" ) {
                                                                                                            $writing_style = "beskrivande";
                                                                                                        } else {
                                                                                                            $writing_style = "descriptive";
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        }
                    
                    }
                
                }
            
            }
        
        } else {
            
            if ( $qcld_writing_style == "creat" ) {
                
                if ( $qcld_language == "en" ) {
                    $writing_style = "creative";
                } else {
                    
                    if ( $qcld_language == "de" ) {
                        $writing_style = "kreativ";
                    } else {
                        
                        if ( $qcld_language == "fr" ) {
                            $writing_style = "créatif";
                        } else {
                            
                            if ( $qcld_language == "es" ) {
                                $writing_style = "creativo";
                            } else {
                                
                                if ( $qcld_language == "it" ) {
                                    $writing_style = "creativo";
                                } else {
                                    
                                    if ( $qcld_language == "pt" ) {
                                        $writing_style = "criativo";
                                    } else {
                                        
                                        if ( $qcld_language == "nl" ) {
                                            $writing_style = "creatief";
                                        } else {
                                            
                                            if ( $qcld_language == "ru" ) {
                                                $writing_style = "творческий";
                                            } else {
                                                
                                                if ( $qcld_language == "ja" ) {
                                                    $writing_style = "クリエイティブ";
                                                } else {
                                                    
                                                    if ( $qcld_language == "ko" ) {
                                                        $writing_style = "창의적인";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "zh" ) {
                                                            $writing_style = "创造性";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "ko" ) {
                                                                $writing_style = "창의적인";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "id" ) {
                                                                    $writing_style = "kreatif";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "tr" ) {
                                                                        $writing_style = "yaratıcı";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "hi" ) {
                                                                            $writing_style = "रचनात्मक";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "pl" ) {
                                                                                $writing_style = "twórczy";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "uk" ) {
                                                                                    $writing_style = "творчий";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ar" ) {
                                                                                        $writing_style = "إبداعي";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ro" ) {
                                                                                            $writing_style = "creativ";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "hu" ) {
                                                                                                $writing_style = "kreatív";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "cs" ) {
                                                                                                    $writing_style = "tvůrčí";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "el" ) {
                                                                                                        $writing_style = "δημιουργικός";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "bg" ) {
                                                                                                            $writing_style = "творчески";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "sv" ) {
                                                                                                                $writing_style = "kreativ";
                                                                                                            } else {
                                                                                                                $writing_style = "creative";
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        }
                    
                    }
                
                }
            
            } else {
                
                if ( $qcld_writing_style == "narra" ) {
                    
                    if ( $qcld_language == "en" ) {
                        $writing_style = "narrative";
                    } else {
                        
                        if ( $qcld_language == "de" ) {
                            $writing_style = "narrativ";
                        } else {
                            
                            if ( $qcld_language == "fr" ) {
                                $writing_style = "narratif";
                            } else {
                                
                                if ( $qcld_language == "es" ) {
                                    $writing_style = "narrativo";
                                } else {
                                    
                                    if ( $qcld_language == "it" ) {
                                        $writing_style = "narrativo";
                                    } else {
                                        
                                        if ( $qcld_language == "pt" ) {
                                            $writing_style = "narrativo";
                                        } else {
                                            
                                            if ( $qcld_language == "nl" ) {
                                                $writing_style = "narratief";
                                            } else {
                                                
                                                if ( $qcld_language == "ru" ) {
                                                    $writing_style = "рассказ";
                                                } else {
                                                    
                                                    if ( $qcld_language == "ja" ) {
                                                        $writing_style = "物語";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "ko" ) {
                                                            $writing_style = "이야기";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "zh" ) {
                                                                $writing_style = "叙事";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "ko" ) {
                                                                    $writing_style = "이야기";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "id" ) {
                                                                        $writing_style = "narratif";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "tr" ) {
                                                                            $writing_style = "anlatıcı";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "hi" ) {
                                                                                $writing_style = "कथा";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "pl" ) {
                                                                                    $writing_style = "opowiadanie";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "uk" ) {
                                                                                        $writing_style = "розповідь";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ar" ) {
                                                                                            $writing_style = "قصة";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ro" ) {
                                                                                                $writing_style = "narrativ";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "hu" ) {
                                                                                                    $writing_style = "narratív";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "cs" ) {
                                                                                                        $writing_style = "příběh";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "el" ) {
                                                                                                            $writing_style = "περιγραφικός";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "bg" ) {
                                                                                                                $writing_style = "рассказ";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "sv" ) {
                                                                                                                    $writing_style = "narrativ";
                                                                                                                } else {
                                                                                                                    $writing_style = "narrative";
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        }
                    
                    }
                
                } else {
                    
                    if ( $qcld_writing_style == "persu" ) {
                        
                        if ( $qcld_language == "en" ) {
                            $writing_style = "persuasive";
                        } else {
                            
                            if ( $qcld_language == "de" ) {
                                $writing_style = "überzeugend";
                            } else {
                                
                                if ( $qcld_language == "fr" ) {
                                    $writing_style = "persuasif";
                                } else {
                                    
                                    if ( $qcld_language == "es" ) {
                                        $writing_style = "persuasivo";
                                    } else {
                                        
                                        if ( $qcld_language == "it" ) {
                                            $writing_style = "persuasivo";
                                        } else {
                                            
                                            if ( $qcld_language == "pt" ) {
                                                $writing_style = "persuasivo";
                                            } else {
                                                
                                                if ( $qcld_language == "nl" ) {
                                                    $writing_style = "overtuigend";
                                                } else {
                                                    
                                                    if ( $qcld_language == "ru" ) {
                                                        $writing_style = "убеждение";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "ja" ) {
                                                            $writing_style = "説得力のある";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "ko" ) {
                                                                $writing_style = "설득력 있는";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "zh" ) {
                                                                    $writing_style = "说服力";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "ko" ) {
                                                                        $writing_style = "설득력 있는";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "id" ) {
                                                                            $writing_style = "persuasif";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "tr" ) {
                                                                                $writing_style = "ikna edici";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "hi" ) {
                                                                                    $writing_style = "प्रेरक";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "pl" ) {
                                                                                        $writing_style = "przekonywujący";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "uk" ) {
                                                                                            $writing_style = "переконливий";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ar" ) {
                                                                                                $writing_style = "مقنع";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ro" ) {
                                                                                                    $writing_style = "persuasiv";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "hu" ) {
                                                                                                        $writing_style = "persuasív";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "cs" ) {
                                                                                                            $writing_style = "přesvědčivý";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "el" ) {
                                                                                                                $writing_style = "περιεκτικός";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "bg" ) {
                                                                                                                    $writing_style = "привличащ";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "sv" ) {
                                                                                                                        $writing_style = "persuasiv";
                                                                                                                    } else {
                                                                                                                        $writing_style = "persuasive";
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        }
                    
                    } else {
                        
                        if ( $qcld_writing_style == "expos" ) {
                            
                            if ( $qcld_language == "en" ) {
                                $writing_style = "expository";
                            } else {
                                
                                if ( $qcld_language == "de" ) {
                                    $writing_style = "erklärend";
                                } else {
                                    
                                    if ( $qcld_language == "fr" ) {
                                        $writing_style = "exposé";
                                    } else {
                                        
                                        if ( $qcld_language == "es" ) {
                                            $writing_style = "expositivo";
                                        } else {
                                            
                                            if ( $qcld_language == "it" ) {
                                                $writing_style = "espositivo";
                                            } else {
                                                
                                                if ( $qcld_language == "pt" ) {
                                                    $writing_style = "expositivo";
                                                } else {
                                                    
                                                    if ( $qcld_language == "nl" ) {
                                                        $writing_style = "expositief";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "ru" ) {
                                                            $writing_style = "описательный";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "ja" ) {
                                                                $writing_style = "説明的";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "ko" ) {
                                                                    $writing_style = "설명적인";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "zh" ) {
                                                                        $writing_style = "说明性";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "ko" ) {
                                                                            $writing_style = "설명적인";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "id" ) {
                                                                                $writing_style = "eksposisi";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "tr" ) {
                                                                                    $writing_style = "açıklayıcı";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "hi" ) {
                                                                                        $writing_style = "व्याख्यात्मक";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "pl" ) {
                                                                                            $writing_style = "wyjaśniający";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "uk" ) {
                                                                                                $writing_style = "описовий";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ar" ) {
                                                                                                    $writing_style = "وصفي";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ro" ) {
                                                                                                        $writing_style = "expozitiv";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "hu" ) {
                                                                                                            $writing_style = "előadó";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "cs" ) {
                                                                                                                $writing_style = "vysvětlující";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "el" ) {
                                                                                                                    $writing_style = "εξηγητικός";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "bg" ) {
                                                                                                                        $writing_style = "обяснителен";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "sv" ) {
                                                                                                                            $writing_style = "expositorisk";
                                                                                                                        } else {
                                                                                                                            $writing_style = "expository";
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        } else {
                            
                            if ( $qcld_writing_style == "infor" ) {
                                
                                if ( $qcld_language == "en" ) {
                                    $writing_style = "informative";
                                } else {
                                    
                                    if ( $qcld_language == "de" ) {
                                        $writing_style = "informierend";
                                    } else {
                                        
                                        if ( $qcld_language == "fr" ) {
                                            $writing_style = "informatif";
                                        } else {
                                            
                                            if ( $qcld_language == "es" ) {
                                                $writing_style = "informativo";
                                            } else {
                                                
                                                if ( $qcld_language == "it" ) {
                                                    $writing_style = "informativo";
                                                } else {
                                                    
                                                    if ( $qcld_language == "pt" ) {
                                                        $writing_style = "informativo";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "nl" ) {
                                                            $writing_style = "informatief";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "ru" ) {
                                                                $writing_style = "информативный";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "ja" ) {
                                                                    $writing_style = "情報";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "ko" ) {
                                                                        $writing_style = "정보";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "zh" ) {
                                                                            $writing_style = "信息";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "ko" ) {
                                                                                $writing_style = "정보";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "id" ) {
                                                                                    $writing_style = "informasi";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "tr" ) {
                                                                                        $writing_style = "bilgilendirici";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "hi" ) {
                                                                                            $writing_style = "सूचनात्मक";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "pl" ) {
                                                                                                $writing_style = "informacyjny";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "uk" ) {
                                                                                                    $writing_style = "інформативний";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ar" ) {
                                                                                                        $writing_style = "معلوماتي";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ro" ) {
                                                                                                            $writing_style = "informativ";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "hu" ) {
                                                                                                                $writing_style = "informáló";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "cs" ) {
                                                                                                                    $writing_style = "informační";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "el" ) {
                                                                                                                        $writing_style = "πληροφοριακός";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "bg" ) {
                                                                                                                            $writing_style = "информативен";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "sv" ) {
                                                                                                                                $writing_style = "informativ";
                                                                                                                            } else {
                                                                                                                                $writing_style = "informative";
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                                
                                // add reflective
                            } else {
                                
                                if ( $qcld_writing_style == "refle" ) {
                                    
                                    if ( $qcld_language == "en" ) {
                                        $writing_style = "reflective";
                                    } else {
                                        
                                        if ( $qcld_language == "de" ) {
                                            $writing_style = "reflektierend";
                                        } else {
                                            
                                            if ( $qcld_language == "fr" ) {
                                                $writing_style = "réfléchi";
                                            } else {
                                                
                                                if ( $qcld_language == "es" ) {
                                                    $writing_style = "reflexivo";
                                                } else {
                                                    
                                                    if ( $qcld_language == "it" ) {
                                                        $writing_style = "reflessivo";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "pt" ) {
                                                            $writing_style = "reflexivo";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "nl" ) {
                                                                $writing_style = "reflectief";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "ru" ) {
                                                                    $writing_style = "рефлексивный";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "ja" ) {
                                                                        $writing_style = "反射的";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "ko" ) {
                                                                            $writing_style = "반사적인";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "zh" ) {
                                                                                $writing_style = "反射的";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "ko" ) {
                                                                                    $writing_style = "반사적인";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "id" ) {
                                                                                        $writing_style = "reflektif";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "tr" ) {
                                                                                            $writing_style = "yansıtıcı";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "hi" ) {
                                                                                                $writing_style = "चिंतनशील";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "pl" ) {
                                                                                                    $writing_style = "refleksyjny";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "uk" ) {
                                                                                                        $writing_style = "рефлексивний";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ar" ) {
                                                                                                            $writing_style = "مرئي";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "ro" ) {
                                                                                                                $writing_style = "reflectiv";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "hu" ) {
                                                                                                                    $writing_style = "visszatükröző";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "cs" ) {
                                                                                                                        $writing_style = "reflexní";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "el" ) {
                                                                                                                            $writing_style = "αναστρεφτικός";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "bg" ) {
                                                                                                                                $writing_style = "рефлексивен";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "sv" ) {
                                                                                                                                    $writing_style = "reflekterande";
                                                                                                                                } else {
                                                                                                                                    $writing_style = "reflective";
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                    
                                    // add argumentative
                                } else {
                                    
                                    if ( $qcld_writing_style == "argum" ) {
                                        
                                        if ( $qcld_language == "en" ) {
                                            $writing_style = "argumentative";
                                        } else {
                                            
                                            if ( $qcld_language == "de" ) {
                                                $writing_style = "argumentativ";
                                            } else {
                                                
                                                if ( $qcld_language == "fr" ) {
                                                    $writing_style = "argumentatif";
                                                } else {
                                                    
                                                    if ( $qcld_language == "es" ) {
                                                        $writing_style = "argumentativo";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "it" ) {
                                                            $writing_style = "argomentativo";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "pt" ) {
                                                                $writing_style = "argumentativo";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "nl" ) {
                                                                    $writing_style = "argumentatief";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "ru" ) {
                                                                        $writing_style = "аргументативный";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "ja" ) {
                                                                            $writing_style = "論争的";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "ko" ) {
                                                                                $writing_style = "논쟁적인";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "zh" ) {
                                                                                    $writing_style = "争论的";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ko" ) {
                                                                                        $writing_style = "논쟁적인";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "id" ) {
                                                                                            $writing_style = "argumentatif";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "tr" ) {
                                                                                                $writing_style = "tartışmalı";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "hi" ) {
                                                                                                    $writing_style = "विवादात्मक";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "pl" ) {
                                                                                                        $writing_style = "argumentacyjny";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "uk" ) {
                                                                                                            $writing_style = "аргументативний";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "ar" ) {
                                                                                                                $writing_style = "مناقشة";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "ro" ) {
                                                                                                                    $writing_style = "argumentativ";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "hu" ) {
                                                                                                                        $writing_style = "vitatható";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "cs" ) {
                                                                                                                            $writing_style = "argumentativní";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "el" ) {
                                                                                                                                $writing_style = "αργυρολογικός";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "bg" ) {
                                                                                                                                    $writing_style = "аргументативен";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "sv" ) {
                                                                                                                                        $writing_style = "argumentativ";
                                                                                                                                    } else {
                                                                                                                                        $writing_style = "argumentative";
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    } else {
                                        
                                        if ( $qcld_writing_style == "analy" ) {
                                            
                                            if ( $qcld_language == "en" ) {
                                                $writing_style = "analytical";
                                            } else {
                                                
                                                if ( $qcld_language == "de" ) {
                                                    $writing_style = "analytisch";
                                                } else {
                                                    
                                                    if ( $qcld_language == "fr" ) {
                                                        $writing_style = "analytique";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "es" ) {
                                                            $writing_style = "analítico";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "it" ) {
                                                                $writing_style = "analitico";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "pt" ) {
                                                                    $writing_style = "analítico";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "nl" ) {
                                                                        $writing_style = "analytisch";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "ru" ) {
                                                                            $writing_style = "аналитический";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "ja" ) {
                                                                                $writing_style = "分析的";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "ko" ) {
                                                                                    $writing_style = "분석적인";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "zh" ) {
                                                                                        $writing_style = "分析的";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ko" ) {
                                                                                            $writing_style = "분석적인";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "id" ) {
                                                                                                $writing_style = "analitis";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "tr" ) {
                                                                                                    $writing_style = "analitik";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "hi" ) {
                                                                                                        $writing_style = "विश्लेषणीय";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "pl" ) {
                                                                                                            $writing_style = "analizujący";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "uk" ) {
                                                                                                                $writing_style = "аналітичний";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "ar" ) {
                                                                                                                    $writing_style = "تحليلي";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "ro" ) {
                                                                                                                        $writing_style = "analitic";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "hu" ) {
                                                                                                                            $writing_style = "elemző";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "cs" ) {
                                                                                                                                $writing_style = "analytický";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "el" ) {
                                                                                                                                    $writing_style = "αναλυτικός";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "bg" ) {
                                                                                                                                        $writing_style = "аналитичен";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "sv" ) {
                                                                                                                                            $writing_style = "analytisk";
                                                                                                                                        } else {
                                                                                                                                            $writing_style = "analytical";
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        } else {
                                            
                                            if ( $qcld_writing_style == "criti" ) {
                                                
                                                if ( $qcld_language == "en" ) {
                                                    $writing_style = "critical";
                                                } else {
                                                    
                                                    if ( $qcld_language == "de" ) {
                                                        $writing_style = "kritisch";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "fr" ) {
                                                            $writing_style = "critique";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "es" ) {
                                                                $writing_style = "crítico";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "it" ) {
                                                                    $writing_style = "critico";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "pt" ) {
                                                                        $writing_style = "crítico";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "nl" ) {
                                                                            $writing_style = "kritisch";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "ru" ) {
                                                                                $writing_style = "критический";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "ja" ) {
                                                                                    $writing_style = "批判的";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ko" ) {
                                                                                        $writing_style = "비판적인";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "zh" ) {
                                                                                            $writing_style = "批判的";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ko" ) {
                                                                                                $writing_style = "비판적인";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "id" ) {
                                                                                                    $writing_style = "kritikal";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "tr" ) {
                                                                                                        $writing_style = "eleştirel";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "hi" ) {
                                                                                                            $writing_style = "नाजुक";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "pl" ) {
                                                                                                                $writing_style = "krytyczny";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "uk" ) {
                                                                                                                    $writing_style = "критичний";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "ar" ) {
                                                                                                                        $writing_style = "حريص";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "ro" ) {
                                                                                                                            $writing_style = "critic";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "hu" ) {
                                                                                                                                $writing_style = "kritikus";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "cs" ) {
                                                                                                                                    $writing_style = "kritický";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "el" ) {
                                                                                                                                        $writing_style = "κριτικός";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "bg" ) {
                                                                                                                                            $writing_style = "критичен";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "sv" ) {
                                                                                                                                                $writing_style = "kritisk";
                                                                                                                                            } else {
                                                                                                                                                $writing_style = "critical";
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            } else {
                                                
                                                if ( $qcld_writing_style == "evalu" ) {
                                                    
                                                    if ( $qcld_language == "en" ) {
                                                        $writing_style = "evaluative";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "de" ) {
                                                            $writing_style = "evaluierend";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "fr" ) {
                                                                $writing_style = "évaluatif";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "es" ) {
                                                                    $writing_style = "evaluativo";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "it" ) {
                                                                        $writing_style = "valutativo";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "pt" ) {
                                                                            $writing_style = "avaliativo";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "nl" ) {
                                                                                $writing_style = "evaluerend";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "ru" ) {
                                                                                    $writing_style = "оценочный";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ja" ) {
                                                                                        $writing_style = "評価的";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ko" ) {
                                                                                            $writing_style = "평가적인";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "zh" ) {
                                                                                                $writing_style = "评价的";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ko" ) {
                                                                                                    $writing_style = "평가적인";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "id" ) {
                                                                                                        $writing_style = "evaluatif";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "tr" ) {
                                                                                                            $writing_style = "değerlendirici";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "hi" ) {
                                                                                                                $writing_style = "मूल्यांकन करनेवाला";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "pl" ) {
                                                                                                                    $writing_style = "oceniający";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "uk" ) {
                                                                                                                        $writing_style = "оцінювальний";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "ar" ) {
                                                                                                                            $writing_style = "تقييمي";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "ro" ) {
                                                                                                                                $writing_style = "evaluativ";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "hu" ) {
                                                                                                                                    $writing_style = "értékelő";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "cs" ) {
                                                                                                                                        $writing_style = "posuzující";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "el" ) {
                                                                                                                                            $writing_style = "αξιολογητικός";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "bg" ) {
                                                                                                                                                $writing_style = "оценителен";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "sv" ) {
                                                                                                                                                    $writing_style = "bedömning";
                                                                                                                                                } else {
                                                                                                                                                    $writing_style = "evaluative";
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                } else {
                                                    
                                                    if ( $qcld_writing_style == "journ" ) {
                                                        
                                                        if ( $qcld_language == "en" ) {
                                                            $writing_style = "journalistic";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "de" ) {
                                                                $writing_style = "journalistisch";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "fr" ) {
                                                                    $writing_style = "journalistique";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "es" ) {
                                                                        $writing_style = "periodístico";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "it" ) {
                                                                            $writing_style = "giornalistico";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "pt" ) {
                                                                                $writing_style = "jornalístico";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "nl" ) {
                                                                                    $writing_style = "journalistiek";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ru" ) {
                                                                                        $writing_style = "журналистский";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ja" ) {
                                                                                            $writing_style = "ジャーナリスト";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ko" ) {
                                                                                                $writing_style = "저널리스트";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "zh" ) {
                                                                                                    $writing_style = "记者";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ko" ) {
                                                                                                        $writing_style = "저널리스트";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "id" ) {
                                                                                                            $writing_style = "jurnalistik";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "tr" ) {
                                                                                                                $writing_style = "gazeteci";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "hi" ) {
                                                                                                                    $writing_style = "पत्रकारी";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "pl" ) {
                                                                                                                        $writing_style = "dziennikarski";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "uk" ) {
                                                                                                                            $writing_style = "журналістський";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "ar" ) {
                                                                                                                                $writing_style = "صحفي";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "ro" ) {
                                                                                                                                    $writing_style = "jurnalistic";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "hu" ) {
                                                                                                                                        $writing_style = "újságírói";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "cs" ) {
                                                                                                                                            $writing_style = "žurnalistický";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "el" ) {
                                                                                                                                                $writing_style = "Επαγγελματικός";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "bg" ) {
                                                                                                                                                    $writing_style = "журналистически";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "sv" ) {
                                                                                                                                                        $writing_style = "journalistisk";
                                                                                                                                                    } else {
                                                                                                                                                        $writing_style = "journalistic";
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    } else {
                                                        
                                                        if ( $qcld_writing_style == "techn" ) {
                                                            
                                                            if ( $qcld_language == "en" ) {
                                                                $writing_style = "technical";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "de" ) {
                                                                    $writing_style = "technisch";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "fr" ) {
                                                                        $writing_style = "technique";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "es" ) {
                                                                            $writing_style = "técnico";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "it" ) {
                                                                                $writing_style = "tecnico";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "pt" ) {
                                                                                    $writing_style = "técnico";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "nl" ) {
                                                                                        $writing_style = "technisch";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ru" ) {
                                                                                            $writing_style = "технический";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ja" ) {
                                                                                                $writing_style = "技術的";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ko" ) {
                                                                                                    $writing_style = "기술적인";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "zh" ) {
                                                                                                        $writing_style = "技术的";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ko" ) {
                                                                                                            $writing_style = "기술적인";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "id" ) {
                                                                                                                $writing_style = "teknis";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "tr" ) {
                                                                                                                    $writing_style = "teknik";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "hi" ) {
                                                                                                                        $writing_style = "तकनीकी";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "pl" ) {
                                                                                                                            $writing_style = "techniczny";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "uk" ) {
                                                                                                                                $writing_style = "технічний";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "ar" ) {
                                                                                                                                    $writing_style = "فني";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "ro" ) {
                                                                                                                                        $writing_style = "tehnica";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "hu" ) {
                                                                                                                                            $writing_style = "technikai";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "cs" ) {
                                                                                                                                                $writing_style = "technický";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "el" ) {
                                                                                                                                                    $writing_style = "τεχνικός";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "bg" ) {
                                                                                                                                                        $writing_style = "технически";
                                                                                                                                                    } else {
                                                                                                                                                        
                                                                                                                                                        if ( $qcld_language == "sv" ) {
                                                                                                                                                            $writing_style = "teknisk";
                                                                                                                                                        } else {
                                                                                                                                                            $writing_style = "technical";
                                                                                                                                                        }
                                                                                                                                                    
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        } else {
                                                            
                                                            if ( $qcld_writing_style == "repor" ) {
                                                                
                                                                if ( $qcld_language == "en" ) {
                                                                    $writing_style = "report";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "de" ) {
                                                                        $writing_style = "Bericht";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "fr" ) {
                                                                            $writing_style = "rapport";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "es" ) {
                                                                                $writing_style = "informe";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "it" ) {
                                                                                    $writing_style = "rapporto";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "pt" ) {
                                                                                        $writing_style = "relatório";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "nl" ) {
                                                                                            $writing_style = "verslag";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ru" ) {
                                                                                                $writing_style = "отчет";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ja" ) {
                                                                                                    $writing_style = "報告";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ko" ) {
                                                                                                        $writing_style = "보고서";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "zh" ) {
                                                                                                            $writing_style = "报告";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "ko" ) {
                                                                                                                $writing_style = "보고서";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "id" ) {
                                                                                                                    $writing_style = "laporan";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "tr" ) {
                                                                                                                        $writing_style = "rapor";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "hi" ) {
                                                                                                                            $writing_style = "रिपोर्ट";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "pl" ) {
                                                                                                                                $writing_style = "raport";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "uk" ) {
                                                                                                                                    $writing_style = "звіт";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "ar" ) {
                                                                                                                                        $writing_style = "تقرير";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "ro" ) {
                                                                                                                                            $writing_style = "raport";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "hu" ) {
                                                                                                                                                $writing_style = "jelentés";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "cs" ) {
                                                                                                                                                    $writing_style = "zpráva";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "el" ) {
                                                                                                                                                        $writing_style = "αναφορά";
                                                                                                                                                    } else {
                                                                                                                                                        
                                                                                                                                                        if ( $qcld_language == "bg" ) {
                                                                                                                                                            $writing_style = "доклад";
                                                                                                                                                        } else {
                                                                                                                                                            
                                                                                                                                                            if ( $qcld_language == "sv" ) {
                                                                                                                                                                $writing_style = "rapport";
                                                                                                                                                            } else {
                                                                                                                                                                $writing_style = "report";
                                                                                                                                                            }
                                                                                                                                                        
                                                                                                                                                        }
                                                                                                                                                    
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            } else {
                                                                
                                                                if ( $qcld_writing_style == "resea" ) {
                                                                    
                                                                    if ( $qcld_language == "en" ) {
                                                                        $writing_style = "research";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "de" ) {
                                                                            $writing_style = "Forschung";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "fr" ) {
                                                                                $writing_style = "recherche";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "es" ) {
                                                                                    $writing_style = "investigación";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "it" ) {
                                                                                        $writing_style = "ricerca";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "pt" ) {
                                                                                            $writing_style = "pesquisa";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "nl" ) {
                                                                                                $writing_style = "onderzoek";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ru" ) {
                                                                                                    $writing_style = "исследование";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ja" ) {
                                                                                                        $writing_style = "研究";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ko" ) {
                                                                                                            $writing_style = "연구";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "zh" ) {
                                                                                                                $writing_style = "研究";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "ko" ) {
                                                                                                                    $writing_style = "연구";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "id" ) {
                                                                                                                        $writing_style = "penelitian";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "tr" ) {
                                                                                                                            $writing_style = "araştırma";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "hi" ) {
                                                                                                                                $writing_style = "अनुसंधान";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "pl" ) {
                                                                                                                                    $writing_style = "badania";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "uk" ) {
                                                                                                                                        $writing_style = "дослідження";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "ar" ) {
                                                                                                                                            $writing_style = "بحث";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "ro" ) {
                                                                                                                                                $writing_style = "cercetare";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "hu" ) {
                                                                                                                                                    $writing_style = "kutatás";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "cs" ) {
                                                                                                                                                        $writing_style = "výzkum";
                                                                                                                                                    } else {
                                                                                                                                                        
                                                                                                                                                        if ( $qcld_language == "el" ) {
                                                                                                                                                            $writing_style = "ερευνα";
                                                                                                                                                        } else {
                                                                                                                                                            
                                                                                                                                                            if ( $qcld_language == "bg" ) {
                                                                                                                                                                $writing_style = "изследване";
                                                                                                                                                            } else {
                                                                                                                                                                
                                                                                                                                                                if ( $qcld_language == "sv" ) {
                                                                                                                                                                    $writing_style = "forskning";
                                                                                                                                                                } else {
                                                                                                                                                                    $writing_style = "research";
                                                                                                                                                                }
                                                                                                                                                            
                                                                                                                                                            }
                                                                                                                                                        
                                                                                                                                                        }
                                                                                                                                                    
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                } else {
                                                                    
                                                                    if ( $qcld_writing_style == "simpl" ) {
                                                                        
                                                                        if ( $qcld_language == "en" ) {
                                                                            $writing_style = "simple";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "de" ) {
                                                                                $writing_style = "einfach";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "fr" ) {
                                                                                    $writing_style = "simple";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "es" ) {
                                                                                        $writing_style = "simple";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "it" ) {
                                                                                            $writing_style = "semplice";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "pt" ) {
                                                                                                $writing_style = "simples";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "nl" ) {
                                                                                                    $writing_style = "eenvoudig";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ru" ) {
                                                                                                        $writing_style = "простой";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ja" ) {
                                                                                                            $writing_style = "シンプル";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "ko" ) {
                                                                                                                $writing_style = "단순한";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "zh" ) {
                                                                                                                    $writing_style = "简单";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "ko" ) {
                                                                                                                        $writing_style = "단순한";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "id" ) {
                                                                                                                            $writing_style = "sederhana";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "tr" ) {
                                                                                                                                $writing_style = "basit";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "hi" ) {
                                                                                                                                    $writing_style = "सरल";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "pl" ) {
                                                                                                                                        $writing_style = "prosty";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "uk" ) {
                                                                                                                                            $writing_style = "простий";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "ar" ) {
                                                                                                                                                $writing_style = "بسيط";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "ro" ) {
                                                                                                                                                    $writing_style = "simplu";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "hu" ) {
                                                                                                                                                        $writing_style = "egyszerű";
                                                                                                                                                    } else {
                                                                                                                                                        
                                                                                                                                                        if ( $qcld_language == "cs" ) {
                                                                                                                                                            $writing_style = "jednoduchý";
                                                                                                                                                        } else {
                                                                                                                                                            
                                                                                                                                                            if ( $qcld_language == "el" ) {
                                                                                                                                                                $writing_style = "απλός";
                                                                                                                                                            } else {
                                                                                                                                                                
                                                                                                                                                                if ( $qcld_language == "bg" ) {
                                                                                                                                                                    $writing_style = "прост";
                                                                                                                                                                } else {
                                                                                                                                                                    
                                                                                                                                                                    if ( $qcld_language == "sv" ) {
                                                                                                                                                                        $writing_style = "enkel";
                                                                                                                                                                    } else {
                                                                                                                                                                        $writing_style = "simple";
                                                                                                                                                                    }
                                                                                                                                                                
                                                                                                                                                                }
                                                                                                                                                            
                                                                                                                                                            }
                                                                                                                                                        
                                                                                                                                                        }
                                                                                                                                                    
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    } else {
                                                                        // informative
                                                                        
                                                                        if ( $qcld_language == "en" ) {
                                                                            $writing_style = "informative";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "de" ) {
                                                                                $writing_style = "informierend";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "fr" ) {
                                                                                    $writing_style = "informatif";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "es" ) {
                                                                                        $writing_style = "informativo";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "it" ) {
                                                                                            $writing_style = "informativo";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "pt" ) {
                                                                                                $writing_style = "informativo";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "nl" ) {
                                                                                                    $writing_style = "informatief";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ru" ) {
                                                                                                        $writing_style = "информативный";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ja" ) {
                                                                                                            $writing_style = "情報";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "ko" ) {
                                                                                                                $writing_style = "정보";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "zh" ) {
                                                                                                                    $writing_style = "信息";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "ko" ) {
                                                                                                                        $writing_style = "정보";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "id" ) {
                                                                                                                            $writing_style = "informasi";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "tr" ) {
                                                                                                                                $writing_style = "bilgilendirici";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "hi" ) {
                                                                                                                                    $writing_style = "सूचनात्मक";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "pl" ) {
                                                                                                                                        $writing_style = "informacyjny";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "uk" ) {
                                                                                                                                            $writing_style = "інформативний";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "ar" ) {
                                                                                                                                                $writing_style = "معلوماتي";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "ro" ) {
                                                                                                                                                    $writing_style = "informativ";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "hu" ) {
                                                                                                                                                        $writing_style = "információs";
                                                                                                                                                    } else {
                                                                                                                                                        
                                                                                                                                                        if ( $qcld_language == "cs" ) {
                                                                                                                                                            $writing_style = "informační";
                                                                                                                                                        } else {
                                                                                                                                                            
                                                                                                                                                            if ( $qcld_language == "el" ) {
                                                                                                                                                                $writing_style = "πληροφοριακός";
                                                                                                                                                            } else {
                                                                                                                                                                
                                                                                                                                                                if ( $qcld_language == "bg" ) {
                                                                                                                                                                    $writing_style = "информативен";
                                                                                                                                                                } else {
                                                                                                                                                                    
                                                                                                                                                                    if ( $qcld_language == "sv" ) {
                                                                                                                                                                        $writing_style = "informativ";
                                                                                                                                                                    } else {
                                                                                                                                                                        $writing_style = "informative";
                                                                                                                                                                    }
                                                                                                                                                                
                                                                                                                                                                }
                                                                                                                                                            
                                                                                                                                                            }
                                                                                                                                                        
                                                                                                                                                        }
                                                                                                                                                    
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        }
                    
                    }
                
                }
            
            }
        
        }

	return $writing_style;
}
}



add_filter('qc_wpbotpro_floating_openai_filter_for_tone', 'qc_wpbotpro_floating_help_openai_filter_for_tone', 10, 2);
if ( ! function_exists( 'qc_wpbotpro_floating_help_openai_filter_for_tone' ) ) {
function qc_wpbotpro_floating_help_openai_filter_for_tone($qcld_writing_tone, $qcld_language){
    
      
        
        if ( $qcld_writing_tone == "asser" ) {
            
            if ( $qcld_language == "en" ) {
                $tone_text = "Writing tone: assertive";
            } else {
                
                if ( $qcld_language == "es" ) {
                    $tone_text = "Tono de escritura: asertivo";
                } else {
                    
                    if ( $qcld_language == "fr" ) {
                        $tone_text = "Ton d'écriture: assertif";
                    } else {
                        
                        if ( $qcld_language == "de" ) {
                            $tone_text = "Schreibton: durchsetzungsfähig";
                        } else {
                            
                            if ( $qcld_language == "it" ) {
                                $tone_text = "Tono di scrittura: assertivo";
                            } else {
                                
                                if ( $qcld_language == "pt" ) {
                                    $tone_text = "Tom de escrita: assertivo";
                                } else {
                                    
                                    if ( $qcld_language == "nl" ) {
                                        $tone_text = "Schrijftoon: assertief";
                                    } else {
                                        
                                        if ( $qcld_language == "ru" ) {
                                            $tone_text = "Тональность текста: утвердительная";
                                        } else {
                                            
                                            if ( $qcld_language == "ja" ) {
                                                $tone_text = "文章の雰囲気: 自信を持って";
                                            } else {
                                                
                                                if ( $qcld_language == "zh" ) {
                                                    $tone_text = "写作氛围: 自信";
                                                } else {
                                                    
                                                    if ( $qcld_language == "ko" ) {
                                                        $tone_text = "글의 분위기: 자신감";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "id" ) {
                                                            $tone_text = "Tingkah laku menulis: asertif";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "tr" ) {
                                                                $tone_text = "Yazım tonu: iddialı";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "hi" ) {
                                                                    $tone_text = "लेखन स्वर: मुखर";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "pl" ) {
                                                                        $tone_text = "Ton pisania: asertywny";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "uk" ) {
                                                                            $tone_text = "Тон письма: напористий";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "ar" ) {
                                                                                $tone_text = "نغمة الكتابة: حازمة";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "ro" ) {
                                                                                    $tone_text = "Tonalitatea scrisului: asertiv";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "hu" ) {
                                                                                        $tone_text = "Írás hangulata: határozott";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "cs" ) {
                                                                                            $tone_text = "Tón psaní: asertivní";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "el" ) {
                                                                                                $tone_text = "Τόνος συγγραφής: αποφασιστικός";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "bg" ) {
                                                                                                    $tone_text = "Тон на писане: асертивен";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "sv" ) {
                                                                                                        $tone_text = "Skrivton: bestämd";
                                                                                                    } else {
                                                                                                        $tone_text = "Writing tone: assertive";
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        }
                    
                    }
                
                }
            
            }
        
        } else {
            
            if ( $qcld_writing_tone == "cheer" ) {
                
                if ( $qcld_language == "en" ) {
                    $tone_text = "Writing tone: cheerful";
                } else {
                    
                    if ( $qcld_language == "es" ) {
                        $tone_text = "Tono de escritura: alegre";
                    } else {
                        
                        if ( $qcld_language == "fr" ) {
                            $tone_text = "Ton d'écriture: joyeux";
                        } else {
                            
                            if ( $qcld_language == "de" ) {
                                $tone_text = "Schreibton: fröhlich";
                            } else {
                                
                                if ( $qcld_language == "it" ) {
                                    $tone_text = "Tono di scrittura: allegro";
                                } else {
                                    
                                    if ( $qcld_language == "pt" ) {
                                        $tone_text = "Tom de escrita: alegre";
                                    } else {
                                        
                                        if ( $qcld_language == "nl" ) {
                                            $tone_text = "Schrijftoon: vrolijk";
                                        } else {
                                            
                                            if ( $qcld_language == "ru" ) {
                                                $tone_text = "Тональность текста: веселая";
                                            } else {
                                                
                                                if ( $qcld_language == "ja" ) {
                                                    $tone_text = "文章の雰囲気: 楽しい";
                                                } else {
                                                    
                                                    if ( $qcld_language == "zh" ) {
                                                        $tone_text = "写作氛围: 欢乐";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "ko" ) {
                                                            $tone_text = "글의 분위기: 기쁨";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "id" ) {
                                                                $tone_text = "Tingkah laku menulis: ceria";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "tr" ) {
                                                                    $tone_text = "Yazım tonu: neşeli";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "hi" ) {
                                                                        $tone_text = "लेखन स्वर: हर्षित";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "pl" ) {
                                                                            $tone_text = "Ton pisania: radosny";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "uk" ) {
                                                                                $tone_text = "Тон письма: веселий";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "ar" ) {
                                                                                    $tone_text = "نغمة الكتابة: مرح";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ro" ) {
                                                                                        $tone_text = "Tonalitatea scrisului: vesel";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "hu" ) {
                                                                                            $tone_text = "Írás hangulata: vidám";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "cs" ) {
                                                                                                $tone_text = "Tón psaní: veselý";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "el" ) {
                                                                                                    $tone_text = "Τόνος συγγραφής: χαρούμενος";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "bg" ) {
                                                                                                        $tone_text = "Тон на писане: весел";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "sv" ) {
                                                                                                            $tone_text = "Skrivton: glad";
                                                                                                        } else {
                                                                                                            $tone_text = "Writing tone: cheerful";
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        }
                    
                    }
                
                }
            
            } else {
                
                if ( $qcld_writing_tone == "humor" ) {
                    
                    if ( $qcld_language == "en" ) {
                        $tone_text = "Writing tone: humorous";
                    } else {
                        
                        if ( $qcld_language == "es" ) {
                            $tone_text = "Tono de escritura: humorístico";
                        } else {
                            
                            if ( $qcld_language == "fr" ) {
                                $tone_text = "Ton d'écriture: humoristique";
                            } else {
                                
                                if ( $qcld_language == "de" ) {
                                    $tone_text = "Schreibton: humorvoll";
                                } else {
                                    
                                    if ( $qcld_language == "it" ) {
                                        $tone_text = "Tono di scrittura: umoristico";
                                    } else {
                                        
                                        if ( $qcld_language == "pt" ) {
                                            $tone_text = "Tom de escrita: humorístico";
                                        } else {
                                            
                                            if ( $qcld_language == "nl" ) {
                                                $tone_text = "Schrijftoon: humoristisch";
                                            } else {
                                                
                                                if ( $qcld_language == "ru" ) {
                                                    $tone_text = "Тональность текста: юмористическая";
                                                } else {
                                                    
                                                    if ( $qcld_language == "ja" ) {
                                                        $tone_text = "文章の雰囲気: ユーモア";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "zh" ) {
                                                            $tone_text = "写作氛围: 幽默";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "ko" ) {
                                                                $tone_text = "글의 분위기: 유머";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "id" ) {
                                                                    $tone_text = "Tingkah laku menulis: humoris";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "tr" ) {
                                                                        $tone_text = "Yazım tonu: komik";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "hi" ) {
                                                                            $tone_text = "लेखन स्वर: विनोदी";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "pl" ) {
                                                                                $tone_text = "Ton pisania: humorystyczny";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "uk" ) {
                                                                                    $tone_text = "Тон письма: гумористичний";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ar" ) {
                                                                                        $tone_text = "نغمة الكتابة: روح الدعابة";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ro" ) {
                                                                                            $tone_text = "Tonalitatea scrisului: umoristic";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "hu" ) {
                                                                                                $tone_text = "Írás hangulata: humoros";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "cs" ) {
                                                                                                    $tone_text = "Tón psaní: humoristický";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "el" ) {
                                                                                                        $tone_text = "Τόνος συγγραφής: χιουμοριστικός";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "bg" ) {
                                                                                                            $tone_text = "Тон на писане: хумористичен";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "sv" ) {
                                                                                                                $tone_text = "Skrivton: humoristisk";
                                                                                                            } else {
                                                                                                                $tone_text = "Writing tone: humorous";
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        }
                    
                    }
                
                } else {
                    
                    if ( $qcld_writing_tone == "inspi" ) {
                        
                        if ( $qcld_language == "en" ) {
                            $tone_text = "Writing tone: inspirational";
                        } else {
                            
                            if ( $qcld_language == "es" ) {
                                $tone_text = "Tono de escritura: inspirador";
                            } else {
                                
                                if ( $qcld_language == "fr" ) {
                                    $tone_text = "Ton d'écriture: inspirant";
                                } else {
                                    
                                    if ( $qcld_language == "de" ) {
                                        $tone_text = "Schreibton: inspirierend";
                                    } else {
                                        
                                        if ( $qcld_language == "it" ) {
                                            $tone_text = "Tono di scrittura: ispiratore";
                                        } else {
                                            
                                            if ( $qcld_language == "pt" ) {
                                                $tone_text = "Tom de escrita: inspirador";
                                            } else {
                                                
                                                if ( $qcld_language == "nl" ) {
                                                    $tone_text = "Schrijftoon: inspirerend";
                                                } else {
                                                    
                                                    if ( $qcld_language == "ru" ) {
                                                        $tone_text = "Тональность текста: вдохновляющая";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "ja" ) {
                                                            $tone_text = "文章の雰囲気: 活気ある";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "zh" ) {
                                                                $tone_text = "写作氛围: 激励";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "ko" ) {
                                                                    $tone_text = "글의 분위기: 영감";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "id" ) {
                                                                        $tone_text = "Tingkah laku menulis: inspiratif";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "tr" ) {
                                                                            $tone_text = "Yazım tonu: ilham verici";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "hi" ) {
                                                                                $tone_text = "लेखन स्वर: प्रेरणादायक";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "pl" ) {
                                                                                    $tone_text = "Ton pisania: inspirujący";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "uk" ) {
                                                                                        $tone_text = "Тон письма: надихаючий";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ar" ) {
                                                                                            $tone_text = "نغمة الكتابة: ملهمة";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ro" ) {
                                                                                                $tone_text = "Tonalitatea scrisului: inspirator";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "cs" ) {
                                                                                                    $tone_text = "Tón psaní: inspirativní";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "hu" ) {
                                                                                                        $tone_text = "Írás hangulata: inspiráló";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "el" ) {
                                                                                                            $tone_text = "Τόνος συγγραφής: εμπνευστικός";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "bg" ) {
                                                                                                                $tone_text = "Тон на писане: вдъхновяващ";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "sv" ) {
                                                                                                                    $tone_text = "Skrivton: inspirerande";
                                                                                                                } else {
                                                                                                                    $tone_text = "Writing tone: inspirational";
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        }
                    
                    } else {
                        
                        if ( $qcld_writing_tone == "sarca" ) {
                            
                            if ( $qcld_language == "en" ) {
                                $tone_text = "Writing tone: sarcastic";
                            } else {
                                
                                if ( $qcld_language == "es" ) {
                                    $tone_text = "Tono de escritura: sarcástico";
                                } else {
                                    
                                    if ( $qcld_language == "fr" ) {
                                        $tone_text = "Ton d'écriture: sarcastique";
                                    } else {
                                        
                                        if ( $qcld_language == "de" ) {
                                            $tone_text = "Schreibton: sarkastisch";
                                        } else {
                                            
                                            if ( $qcld_language == "it" ) {
                                                $tone_text = "Tono di scrittura: sarcastico";
                                            } else {
                                                
                                                if ( $qcld_language == "pt" ) {
                                                    $tone_text = "Tom de escrita: sarcástico";
                                                } else {
                                                    
                                                    if ( $qcld_language == "nl" ) {
                                                        $tone_text = "Schrijftoon: sarcastisch";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "ru" ) {
                                                            $tone_text = "Тональность текста: саркастическая";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "ja" ) {
                                                                $tone_text = "文章の雰囲気: 皮肉";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "zh" ) {
                                                                    $tone_text = "写作氛围: 讽刺";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "ko" ) {
                                                                        $tone_text = "글의 분위기: 비꼬는";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "id" ) {
                                                                            $tone_text = "Tingkah laku menulis: sarkastis";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "tr" ) {
                                                                                $tone_text = "Yazım tonu: sarkastik";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "hi" ) {
                                                                                    $tone_text = "लेखन स्वर: व्यंग्यात्मक";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "pl" ) {
                                                                                        $tone_text = "Ton pisania: sarkastyczny";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "uk" ) {
                                                                                            $tone_text = "Тон письма: саркастичний";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ar" ) {
                                                                                                $tone_text = "نغمة الكتابة: ساخرة";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ro" ) {
                                                                                                    $tone_text = "Tonalitatea scrisului: sarkastic";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "cs" ) {
                                                                                                        $tone_text = "Tón psaní: sarkastický";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "hu" ) {
                                                                                                            $tone_text = "Írás hangulata: szarkasztikus";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "el" ) {
                                                                                                                $tone_text = "Τόνος συγγραφής: σαρκαστικός";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "bg" ) {
                                                                                                                    $tone_text = "Тон на писане: саркастичен";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "sv" ) {
                                                                                                                        $tone_text = "Skrivton: sarkastisk";
                                                                                                                    } else {
                                                                                                                        $tone_text = "Writing tone: sarcastic";
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        } else {
                            
                            if ( $qcld_writing_tone == "informal" ) {
                                
                                if ( $qcld_language == "en" ) {
                                    $tone_text = "Writing tone: informal";
                                } else {
                                    
                                    if ( $qcld_language == "es" ) {
                                        $tone_text = "Tono de escritura: informal";
                                    } else {
                                        
                                        if ( $qcld_language == "fr" ) {
                                            $tone_text = "Ton d'écriture: informel";
                                        } else {
                                            
                                            if ( $qcld_language == "de" ) {
                                                $tone_text = "Schreibton: informell";
                                            } else {
                                                
                                                if ( $qcld_language == "it" ) {
                                                    $tone_text = "Tono di scrittura: informale";
                                                } else {
                                                    
                                                    if ( $qcld_language == "pt" ) {
                                                        $tone_text = "Tom de escrita: informal";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "nl" ) {
                                                            $tone_text = "Schrijftoon: informeel";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "ru" ) {
                                                                $tone_text = "Тональность текста: неформальная";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "ja" ) {
                                                                    $tone_text = "文章の雰囲気: 非公式";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "zh" ) {
                                                                        $tone_text = "写作氛围: 非正式";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "ko" ) {
                                                                            $tone_text = "글의 분위기: 비공식";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "id" ) {
                                                                                $tone_text = "Tingkah laku menulis: tidak resmi";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "tr" ) {
                                                                                    $tone_text = "Yazım tonu: resmi olmayan";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "hi" ) {
                                                                                        $tone_text = "लेखन स्वर: अनौपचारिक";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "pl" ) {
                                                                                            $tone_text = "Ton pisania: nieformalny";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "uk" ) {
                                                                                                $tone_text = "Тон письма: неформальний";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ar" ) {
                                                                                                    $tone_text = "لهجة الكتابة: غير رسمية";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ro" ) {
                                                                                                        $tone_text = "Tonalitatea scrisului: neformal";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "cs" ) {
                                                                                                            $tone_text = "Tón psaní: neformální";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "hu" ) {
                                                                                                                $tone_text = "Írás hangulata: nemformális";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "el" ) {
                                                                                                                    $tone_text = "Τόνος συγγραφής: μη επίσημος";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "bg" ) {
                                                                                                                        $tone_text = "Тон на писане: неформален";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "sv" ) {
                                                                                                                            $tone_text = "Skrivton: informell";
                                                                                                                        } else {
                                                                                                                            $tone_text = "Writing tone: informal";
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            } else {
                                
                                if ( $qcld_writing_tone == "neutr" ) {
                                    
                                    if ( $qcld_language == "en" ) {
                                        $tone_text = "Writing tone: neutral";
                                    } else {
                                        
                                        if ( $qcld_language == "es" ) {
                                            $tone_text = "Tono de escritura: neutral";
                                        } else {
                                            
                                            if ( $qcld_language == "fr" ) {
                                                $tone_text = "Ton d'écriture: neutre";
                                            } else {
                                                
                                                if ( $qcld_language == "de" ) {
                                                    $tone_text = "Schreibton: neutral";
                                                } else {
                                                    
                                                    if ( $qcld_language == "it" ) {
                                                        $tone_text = "Tono di scrittura: neutrale";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "pt" ) {
                                                            $tone_text = "Tom de escrita: neutro";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "nl" ) {
                                                                $tone_text = "Schrijftoon: neutraal";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "ru" ) {
                                                                    $tone_text = "Тональность текста: нейтральная";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "ja" ) {
                                                                        $tone_text = "文章の雰囲気: 中立";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "zh" ) {
                                                                            $tone_text = "写作氛围: 中性";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "ko" ) {
                                                                                $tone_text = "글의 분위기: 중립";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "id" ) {
                                                                                    $tone_text = "Tingkah laku menulis: netral";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "tr" ) {
                                                                                        $tone_text = "Yazım tonu: nötr";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "hi" ) {
                                                                                            $tone_text = "राइटिंग टोन: न्यूट्रल";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "pl" ) {
                                                                                                $tone_text = "Ton pisania: neutralny";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "uk" ) {
                                                                                                    $tone_text = "Тон письма: нейтральний";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ar" ) {
                                                                                                        $tone_text = "نغمة الكتابة: محايدة";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ro" ) {
                                                                                                            $tone_text = "Tonalitatea scrisului: neutru";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "cs" ) {
                                                                                                                $tone_text = "Tón psaní: neutrální";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "hu" ) {
                                                                                                                    $tone_text = "Írás hangulata: semleges";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "el" ) {
                                                                                                                        $tone_text = "Τόνος συγγραφής: ουδέτερος";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "bg" ) {
                                                                                                                            $tone_text = "Тон на писане: нейтрален";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "sv" ) {
                                                                                                                                $tone_text = "Skrivton: neutral";
                                                                                                                            } else {
                                                                                                                                $tone_text = "Writing tone: neutral";
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                } else {
                                    
                                    if ( $qcld_writing_tone == "formal" ) {
                                        
                                        if ( $qcld_language == "en" ) {
                                            $tone_text = "Writing tone: formal";
                                        } else {
                                            
                                            if ( $qcld_language == "es" ) {
                                                $tone_text = "Tono de escritura: formal";
                                            } else {
                                                
                                                if ( $qcld_language == "fr" ) {
                                                    $tone_text = "Ton d'écriture: formel";
                                                } else {
                                                    
                                                    if ( $qcld_language == "de" ) {
                                                        $tone_text = "Schreibton: formell";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "it" ) {
                                                            $tone_text = "Tono di scrittura: formale";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "pt" ) {
                                                                $tone_text = "Tom de escrita: formal";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "nl" ) {
                                                                    $tone_text = "Schrijftoon: formeel";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "ru" ) {
                                                                        $tone_text = "Тональность текста: формальная";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "ja" ) {
                                                                            $tone_text = "文章の雰囲気: 正式";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "zh" ) {
                                                                                $tone_text = "写作氛围: 正式";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "ko" ) {
                                                                                    $tone_text = "글의 분위기: 공식";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "id" ) {
                                                                                        $tone_text = "Tingkah laku menulis: resmi";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "tr" ) {
                                                                                            $tone_text = "Yazım tonu: resmi";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "hi" ) {
                                                                                                $tone_text = "लेखन स्वर: औपचारिक";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "pl" ) {
                                                                                                    $tone_text = "Ton pisania: formalny";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "uk" ) {
                                                                                                        $tone_text = "Тон письма: формальний";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ar" ) {
                                                                                                            $tone_text = "نغمة الكتابة: رسمية";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "ro" ) {
                                                                                                                $tone_text = "Tonalitatea scrisului: formal";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "cs" ) {
                                                                                                                    $tone_text = "Tón psaní: formální";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "hu" ) {
                                                                                                                        $tone_text = "Írás hangulata: hivatalos";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "el" ) {
                                                                                                                            $tone_text = "Τόνος συγγραφής: επίσημος";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "bg" ) {
                                                                                                                                $tone_text = "Тон на писане: формален";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "sv" ) {
                                                                                                                                    $tone_text = "Skrivton: formell";
                                                                                                                                } else {
                                                                                                                                    $tone_text = "Writing tone: formal";
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    } else {
                                        
                                        if ( $qcld_writing_tone == "skept" ) {
                                            
                                            if ( $qcld_language == "en" ) {
                                                $tone_text = "Writing tone: skeptical";
                                            } else {
                                                
                                                if ( $qcld_language == "es" ) {
                                                    $tone_text = "Tono de escritura: escéptico";
                                                } else {
                                                    
                                                    if ( $qcld_language == "fr" ) {
                                                        $tone_text = "Ton d'écriture: sceptique";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "de" ) {
                                                            $tone_text = "Schreibton: skeptisch";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "it" ) {
                                                                $tone_text = "Tono di scrittura: scettico";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "pt" ) {
                                                                    $tone_text = "Tom de escrita: cético";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "nl" ) {
                                                                        $tone_text = "Schrijftoon: sceptisch";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "ru" ) {
                                                                            $tone_text = "Тональность текста: скептическая";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "ja" ) {
                                                                                $tone_text = "文章の雰囲気: 思いやりのない";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "zh" ) {
                                                                                    $tone_text = "写作氛围: 怀疑";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ko" ) {
                                                                                        $tone_text = "글의 분위기: 의심";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "id" ) {
                                                                                            $tone_text = "Tingkah laku menulis: skeptis";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "tr" ) {
                                                                                                $tone_text = "Yazım tonu: şüpheci";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "hi" ) {
                                                                                                    $tone_text = "लेखन स्वर: संदेहवादी";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "pl" ) {
                                                                                                        $tone_text = "Ton pisania: sceptyczny";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "uk" ) {
                                                                                                            $tone_text = "Тон письма: скептичний";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "ar" ) {
                                                                                                                $tone_text = "لهجة الكتابة: متشكك";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "ro" ) {
                                                                                                                    $tone_text = "Tonalitatea scrisului: sceptic";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "cs" ) {
                                                                                                                        $tone_text = "Tón psaní: skeptický";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "hu" ) {
                                                                                                                            $tone_text = "Írás hangulata: kételyes";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "el" ) {
                                                                                                                                $tone_text = "Τόνος συγγραφής: αμφιβαλλόμενος";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "bg" ) {
                                                                                                                                    $tone_text = "Тон на писане: скептичен";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "sv" ) {
                                                                                                                                        $tone_text = "Skrivton: skeptisk";
                                                                                                                                    } else {
                                                                                                                                        $tone_text = "Writing tone: skeptical";
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        } else {
                                            
                                            if ( $qcld_writing_tone == "optim" ) {
                                                
                                                if ( $qcld_language == "en" ) {
                                                    $tone_text = "Writing tone: optimistic";
                                                } else {
                                                    
                                                    if ( $qcld_language == "es" ) {
                                                        $tone_text = "Tono de escritura: optimista";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "fr" ) {
                                                            $tone_text = "Ton d'écriture: optimiste";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "de" ) {
                                                                $tone_text = "Schreibton: optimistisch";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "it" ) {
                                                                    $tone_text = "Tono di scrittura: ottimista";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "pt" ) {
                                                                        $tone_text = "Tom de escrita: otimista";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "nl" ) {
                                                                            $tone_text = "Schrijftoon: optimistisch";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "ru" ) {
                                                                                $tone_text = "Тональность текста: оптимистичная";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "ja" ) {
                                                                                    $tone_text = "文章の雰囲気: 楽観的";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "zh" ) {
                                                                                        $tone_text = "写作氛围: 乐观";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ko" ) {
                                                                                            $tone_text = "글의 분위기: 낙관적";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "id" ) {
                                                                                                $tone_text = "Tingkah laku menulis: optimis";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "tr" ) {
                                                                                                    $tone_text = "Yazım tonu: umutlu";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "hi" ) {
                                                                                                        $tone_text = "लेखन स्वर: उम्मीदवादी";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "pl" ) {
                                                                                                            $tone_text = "Ton pisania: optymistyczny";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "uk" ) {
                                                                                                                $tone_text = "Тон письма: оптимістичний";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "ar" ) {
                                                                                                                    $tone_text = "نغمة الكتابة: متفائلة";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "ro" ) {
                                                                                                                        $tone_text = "Tonalitatea scrisului: optimist";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "cs" ) {
                                                                                                                            $tone_text = "Tón psaní: optimistický";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "hu" ) {
                                                                                                                                $tone_text = "Írás hangulata: optimista";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "el" ) {
                                                                                                                                    $tone_text = "Τόνος συγγραφής: θετικός";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "bg" ) {
                                                                                                                                        $tone_text = "Тон на писане: оптимистичен";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "sv" ) {
                                                                                                                                            $tone_text = "Skrivton: optimistisk";
                                                                                                                                        } else {
                                                                                                                                            $tone_text = "Writing tone: optimistic";
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            } else {
                                                
                                                if ( $qcld_writing_tone == "worry" ) {
                                                    
                                                    if ( $qcld_language == "en" ) {
                                                        $tone_text = "Writing tone: worried";
                                                    } else {
                                                        
                                                        if ( $qcld_language == "es" ) {
                                                            $tone_text = "Tono de escritura: preocupado";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "fr" ) {
                                                                $tone_text = "Ton d'écriture: inquiet";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "de" ) {
                                                                    $tone_text = "Schreibton: besorgt";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "it" ) {
                                                                        $tone_text = "Tono di scrittura: preoccupato";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "pt" ) {
                                                                            $tone_text = "Tom de escrita: preocupado";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "nl" ) {
                                                                                $tone_text = "Schrijftoon: bezorgd";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "ru" ) {
                                                                                    $tone_text = "Тональность текста: беспокойная";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ja" ) {
                                                                                        $tone_text = "文章の雰囲気: 心配";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "zh" ) {
                                                                                            $tone_text = "写作氛围: 忧虑";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ko" ) {
                                                                                                $tone_text = "글의 분위기: 걱정";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "id" ) {
                                                                                                    $tone_text = "Tingkah laku menulis: khawatir";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "tr" ) {
                                                                                                        $tone_text = "Yazım tonu: endişeli";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "hi" ) {
                                                                                                            $tone_text = "लेखन स्वर: चिंतित";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "pl" ) {
                                                                                                                $tone_text = "Ton pisania: zaniepokojony";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "uk" ) {
                                                                                                                    $tone_text = "Тон письма: хвилюваний";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "ar" ) {
                                                                                                                        $tone_text = "نغمة الكتابة: قلق";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "ro" ) {
                                                                                                                            $tone_text = "Tonalitatea scrisului: îngrijorat";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "cs" ) {
                                                                                                                                $tone_text = "Tón psaní: obavam";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "hu" ) {
                                                                                                                                    $tone_text = "Írás hangulata: aggódó";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "el" ) {
                                                                                                                                        $tone_text = "Τόνος συγγραφής: ανησυχημένος";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "bg" ) {
                                                                                                                                            $tone_text = "Тон на писане: притеснен";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "sv" ) {
                                                                                                                                                $tone_text = "Skrivton: orolig";
                                                                                                                                            } else {
                                                                                                                                                $tone_text = "Writing tone: worried";
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                } else {
                                                    
                                                    if ( $qcld_writing_tone == "curio" ) {
                                                        
                                                        if ( $qcld_language == "en" ) {
                                                            $tone_text = "Writing tone: curious";
                                                        } else {
                                                            
                                                            if ( $qcld_language == "es" ) {
                                                                $tone_text = "Tono de escritura: curioso";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "fr" ) {
                                                                    $tone_text = "Ton d'écriture: curieux";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "de" ) {
                                                                        $tone_text = "Schreibton: neugierig";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "it" ) {
                                                                            $tone_text = "Tono di scrittura: curioso";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "pt" ) {
                                                                                $tone_text = "Tom de escrita: curioso";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "nl" ) {
                                                                                    $tone_text = "Schrijftoon: nieuwsgierig";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "ru" ) {
                                                                                        $tone_text = "Тональность текста: любопытная";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ja" ) {
                                                                                            $tone_text = "文章の雰囲気: 好奇心旺盛";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "zh" ) {
                                                                                                $tone_text = "写作氛围: 好奇心旺盛";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ko" ) {
                                                                                                    $tone_text = "글의 분위기: 호기심 많은";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "id" ) {
                                                                                                        $tone_text = "Tingkah laku menulis: penasaran";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "tr" ) {
                                                                                                            $tone_text = "Yazım tonu: meraklı";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "hi" ) {
                                                                                                                $tone_text = "लेखन स्वर: उत्सुक";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "pl" ) {
                                                                                                                    $tone_text = "Ton pisania: ciekawy";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "uk" ) {
                                                                                                                        $tone_text = "Тон письма: цікавий";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "ar" ) {
                                                                                                                            $tone_text = "نغمة الكتابة: فضولي";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "ro" ) {
                                                                                                                                $tone_text = "Tonalitatea scrisului: curios";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "cs" ) {
                                                                                                                                    $tone_text = "Tón psaní: zvědavý";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "hu" ) {
                                                                                                                                        $tone_text = "Írás hangulata: kíváncsi";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "el" ) {
                                                                                                                                            $tone_text = "Τόνος συγγραφής: απορετικός";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "bg" ) {
                                                                                                                                                $tone_text = "Тон на писане: любопитен";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "sv" ) {
                                                                                                                                                    $tone_text = "Skrivton: nyfiken";
                                                                                                                                                } else {
                                                                                                                                                    $tone_text = "Writing tone: curious";
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    } else {
                                                        
                                                        if ( $qcld_writing_tone == "surpr" ) {
                                                            
                                                            if ( $qcld_language == "en" ) {
                                                                $tone_text = "Writing tone: surprised";
                                                            } else {
                                                                
                                                                if ( $qcld_language == "es" ) {
                                                                    $tone_text = "Tono de escritura: sorprendido";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "fr" ) {
                                                                        $tone_text = "Ton d'écriture: surpris";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "de" ) {
                                                                            $tone_text = "Schreibton: überrascht";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "it" ) {
                                                                                $tone_text = "Tono di scrittura: sorpreso";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "pt" ) {
                                                                                    $tone_text = "Tom de escrita: surpreso";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "nl" ) {
                                                                                        $tone_text = "Schrijftoon: verrast";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "ru" ) {
                                                                                            $tone_text = "Тональность текста: удивлённая";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ja" ) {
                                                                                                $tone_text = "文章の雰囲気: 驚いた";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "zh" ) {
                                                                                                    $tone_text = "写作氛围: 惊讶";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ko" ) {
                                                                                                        $tone_text = "글의 분위기: 놀란";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "id" ) {
                                                                                                            $tone_text = "Tingkah laku menulis: terkejut";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "tr" ) {
                                                                                                                $tone_text = "Yazım tonu: şaşırmış";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "hi" ) {
                                                                                                                    $tone_text = "लेखन स्वर: अच्छा नहीं लगा";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "pl" ) {
                                                                                                                        $tone_text = "Ton pisania: zaskoczony";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "uk" ) {
                                                                                                                            $tone_text = "Тон письма: здивований";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "ar" ) {
                                                                                                                                $tone_text = "نغمة الكتابة: مندهش";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "ro" ) {
                                                                                                                                    $tone_text = "Tonalitatea scrisului: surprins";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "cs" ) {
                                                                                                                                        $tone_text = "Tón psaní: překvapený";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "hu" ) {
                                                                                                                                            $tone_text = "Írás hangulata: meglepett";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "el" ) {
                                                                                                                                                $tone_text = "Τόνος συγγραφής: έκπληκτος";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "bg" ) {
                                                                                                                                                    $tone_text = "Тон на писане: изненадан";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "sv" ) {
                                                                                                                                                        $tone_text = "Skrivton: förvånad";
                                                                                                                                                    } else {
                                                                                                                                                        $tone_text = "Writing tone: surprised";
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        } else {
                                                            
                                                            if ( $qcld_writing_tone == "encou" ) {
                                                                
                                                                if ( $qcld_language == "en" ) {
                                                                    $tone_text = "Writing tone: encouraging";
                                                                } else {
                                                                    
                                                                    if ( $qcld_language == "es" ) {
                                                                        $tone_text = "Tono de escritura: alentador";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "fr" ) {
                                                                            $tone_text = "Ton d'écriture: encourageant";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "de" ) {
                                                                                $tone_text = "Schreibton: ermutigend";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "it" ) {
                                                                                    $tone_text = "Tono di scrittura: incoraggiante";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "pt" ) {
                                                                                        $tone_text = "Tom de escrita: encorajador";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "nl" ) {
                                                                                            $tone_text = "Schrijftoon: aanmoedigend";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "ru" ) {
                                                                                                $tone_text = "Тональность текста: поддерживающая";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ja" ) {
                                                                                                    $tone_text = "文章の雰囲気: 勇気づける";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "zh" ) {
                                                                                                        $tone_text = "写作氛围: 鼓励";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ko" ) {
                                                                                                            $tone_text = "글의 분위기: 격려하는";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "id" ) {
                                                                                                                $tone_text = "Tingkah laku menulis: menggalakkan";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "tr" ) {
                                                                                                                    $tone_text = "Yazım tonu: cesaretlendirici";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "hi" ) {
                                                                                                                        $tone_text = "लेखन स्वर: प्रेरणादायक";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "pl" ) {
                                                                                                                            $tone_text = "Ton pisania: zachęcający";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "uk" ) {
                                                                                                                                $tone_text = "Тон письма: підтримуючий";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "ar" ) {
                                                                                                                                    $tone_text = "نغمة الكتابة: مشجعة";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "ro" ) {
                                                                                                                                        $tone_text = "Tonalitatea scrisului: încurajator";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "cs" ) {
                                                                                                                                            $tone_text = "Tón psaní: povzbuzující";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "hu" ) {
                                                                                                                                                $tone_text = "Írás hangulata: bátorító";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "el" ) {
                                                                                                                                                    $tone_text = "Τόνος συγγραφής: ενθαρρυντικός";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "bg" ) {
                                                                                                                                                        $tone_text = "Тон на писане: подкрепяващ";
                                                                                                                                                    } else {
                                                                                                                                                        
                                                                                                                                                        if ( $qcld_language == "sv" ) {
                                                                                                                                                            $tone_text = "Skrivton: uppmuntrande";
                                                                                                                                                        } else {
                                                                                                                                                            $tone_text = "Writing tone: encouraging";
                                                                                                                                                        }
                                                                                                                                                    
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            } else {
                                                                
                                                                if ( $qcld_writing_tone == "disap" ) {
                                                                    
                                                                    if ( $qcld_language == "en" ) {
                                                                        $tone_text = "Writing tone: disappointed";
                                                                    } else {
                                                                        
                                                                        if ( $qcld_language == "es" ) {
                                                                            $tone_text = "Tono de escritura: decepcionado";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "fr" ) {
                                                                                $tone_text = "Ton d'écriture: déçu";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "de" ) {
                                                                                    $tone_text = "Schreibton: enttäuscht";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "it" ) {
                                                                                        $tone_text = "Tono di scrittura: deluso";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "pt" ) {
                                                                                            $tone_text = "Tom de escrita: desapontado";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "nl" ) {
                                                                                                $tone_text = "Schrijftoon: teleurgesteld";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "ru" ) {
                                                                                                    $tone_text = "Тональность текста: расстроенная";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ja" ) {
                                                                                                        $tone_text = "文章の雰囲気: 気の毒";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "zh" ) {
                                                                                                            $tone_text = "写作氛围: 失望";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "ko" ) {
                                                                                                                $tone_text = "글의 분위기: 실망한";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "id" ) {
                                                                                                                    $tone_text = "Tingkah laku menulis: kecewa";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "tr" ) {
                                                                                                                        $tone_text = "Yazım tonu: hayal kırıklığına uğramış";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "hi" ) {
                                                                                                                            $tone_text = "लेखन स्वर: निराशा";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "pl" ) {
                                                                                                                                $tone_text = "Ton pisania: rozczarowany";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "uk" ) {
                                                                                                                                    $tone_text = "Тон письма: розчарований";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "ar" ) {
                                                                                                                                        $tone_text = "لهجة الكتابة: محبط";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "ro" ) {
                                                                                                                                            $tone_text = "Tonalitatea scrisului: dezamăgit";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "cs" ) {
                                                                                                                                                $tone_text = "Tón psaní: zklamaný";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "hu" ) {
                                                                                                                                                    $tone_text = "Írás hangulata: csalódott";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "el" ) {
                                                                                                                                                        $tone_text = "Τόνος συγγραφής: απογοητευμένος";
                                                                                                                                                    } else {
                                                                                                                                                        
                                                                                                                                                        if ( $qcld_language == "bg" ) {
                                                                                                                                                            $tone_text = "Тон на писане: разочарован";
                                                                                                                                                        } else {
                                                                                                                                                            
                                                                                                                                                            if ( $qcld_language == "sv" ) {
                                                                                                                                                                $tone_text = "Skrivton: besviken";
                                                                                                                                                            } else {
                                                                                                                                                                $tone_text = "Writing tone: disappointed";
                                                                                                                                                            }
                                                                                                                                                        
                                                                                                                                                        }
                                                                                                                                                    
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                } else {
                                                                    
                                                                    if ( $qcld_writing_tone == "profe" ) {
                                                                        
                                                                        if ( $qcld_language == "en" ) {
                                                                            $tone_text = "Writing tone: professional";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "es" ) {
                                                                                $tone_text = "Tono de escritura: profesional";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "fr" ) {
                                                                                    $tone_text = "Ton d'écriture: professionnel";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "de" ) {
                                                                                        $tone_text = "Schreibton: professionell";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "it" ) {
                                                                                            $tone_text = "Tono di scrittura: professionale";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "pt" ) {
                                                                                                $tone_text = "Tom de escrita: profissional";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "nl" ) {
                                                                                                    $tone_text = "Schrijftoon: professioneel";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ru" ) {
                                                                                                        $tone_text = "Тональность текста: профессиональная";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ja" ) {
                                                                                                            $tone_text = "文章の雰囲気: プロ";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "zh" ) {
                                                                                                                $tone_text = "写作氛围: 专业";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "ko" ) {
                                                                                                                    $tone_text = "글의 분위기: 전문가";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "id" ) {
                                                                                                                        $tone_text = "Tingkah laku menulis: profesional";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "tr" ) {
                                                                                                                            $tone_text = "Yazım tonu: profesyonel";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "hi" ) {
                                                                                                                                $tone_text = "लेखन स्वर: प्रोफेशनल";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "pl" ) {
                                                                                                                                    $tone_text = "Ton pisania: profesjonalny";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "uk" ) {
                                                                                                                                        $tone_text = "Тон письма: професійний";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "ar" ) {
                                                                                                                                            $tone_text = "لهجة الكتابة: مهني";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "ro" ) {
                                                                                                                                                $tone_text = "Tonalitatea scrisului: profesional";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "cs" ) {
                                                                                                                                                    $tone_text = "Tón psaní: profesionální";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "hu" ) {
                                                                                                                                                        $tone_text = "Írás hangulata: szakmai";
                                                                                                                                                    } else {
                                                                                                                                                        
                                                                                                                                                        if ( $qcld_language == "el" ) {
                                                                                                                                                            $tone_text = "Τόνος συγγραφής: επαγγελματικός";
                                                                                                                                                        } else {
                                                                                                                                                            
                                                                                                                                                            if ( $qcld_language == "bg" ) {
                                                                                                                                                                $tone_text = "Тон на писане: професионален";
                                                                                                                                                            } else {
                                                                                                                                                                
                                                                                                                                                                if ( $qcld_language == "sv" ) {
                                                                                                                                                                    $tone_text = "Skrivton: professionell";
                                                                                                                                                                } else {
                                                                                                                                                                    $tone_text = "Writing tone: professional";
                                                                                                                                                                }
                                                                                                                                                            
                                                                                                                                                            }
                                                                                                                                                        
                                                                                                                                                        }
                                                                                                                                                    
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    } else {
                                                                        // formal
                                                                        
                                                                        if ( $qcld_language == "en" ) {
                                                                            $tone_text = "Writing tone: formal";
                                                                        } else {
                                                                            
                                                                            if ( $qcld_language == "es" ) {
                                                                                $tone_text = "Tono de escritura: formal";
                                                                            } else {
                                                                                
                                                                                if ( $qcld_language == "fr" ) {
                                                                                    $tone_text = "Ton d'écriture: formel";
                                                                                } else {
                                                                                    
                                                                                    if ( $qcld_language == "de" ) {
                                                                                        $tone_text = "Schreibton: formell";
                                                                                    } else {
                                                                                        
                                                                                        if ( $qcld_language == "it" ) {
                                                                                            $tone_text = "Tono di scrittura: formale";
                                                                                        } else {
                                                                                            
                                                                                            if ( $qcld_language == "pt" ) {
                                                                                                $tone_text = "Tom de escrita: formal";
                                                                                            } else {
                                                                                                
                                                                                                if ( $qcld_language == "nl" ) {
                                                                                                    $tone_text = "Schrijftoon: formeel";
                                                                                                } else {
                                                                                                    
                                                                                                    if ( $qcld_language == "ru" ) {
                                                                                                        $tone_text = "Тональность текста: формальная";
                                                                                                    } else {
                                                                                                        
                                                                                                        if ( $qcld_language == "ja" ) {
                                                                                                            $tone_text = "文章の雰囲気: 正式";
                                                                                                        } else {
                                                                                                            
                                                                                                            if ( $qcld_language == "zh" ) {
                                                                                                                $tone_text = "写作氛围: 正式";
                                                                                                            } else {
                                                                                                                
                                                                                                                if ( $qcld_language == "ko" ) {
                                                                                                                    $tone_text = "글의 분위기: 공식";
                                                                                                                } else {
                                                                                                                    
                                                                                                                    if ( $qcld_language == "id" ) {
                                                                                                                        $tone_text = "Tingkah laku menulis: resmi";
                                                                                                                    } else {
                                                                                                                        
                                                                                                                        if ( $qcld_language == "tr" ) {
                                                                                                                            $tone_text = "Yazım tonu: resmi";
                                                                                                                        } else {
                                                                                                                            
                                                                                                                            if ( $qcld_language == "hi" ) {
                                                                                                                                $tone_text = "लेखन स्वर: औपचारिक";
                                                                                                                            } else {
                                                                                                                                
                                                                                                                                if ( $qcld_language == "pl" ) {
                                                                                                                                    $tone_text = "Ton pisania: formalny";
                                                                                                                                } else {
                                                                                                                                    
                                                                                                                                    if ( $qcld_language == "uk" ) {
                                                                                                                                        $tone_text = "Тон письма: формальний";
                                                                                                                                    } else {
                                                                                                                                        
                                                                                                                                        if ( $qcld_language == "ar" ) {
                                                                                                                                            $tone_text = "نغمة الكتابة: رسمية";
                                                                                                                                        } else {
                                                                                                                                            
                                                                                                                                            if ( $qcld_language == "ro" ) {
                                                                                                                                                $tone_text = "Tonalitatea scrisului: formal";
                                                                                                                                            } else {
                                                                                                                                                
                                                                                                                                                if ( $qcld_language == "cs" ) {
                                                                                                                                                    $tone_text = "Tón psaní: formální";
                                                                                                                                                } else {
                                                                                                                                                    
                                                                                                                                                    if ( $qcld_language == "hu" ) {
                                                                                                                                                        $tone_text = "Írás hangulata: hivatalos";
                                                                                                                                                    } else {
                                                                                                                                                        
                                                                                                                                                        if ( $qcld_language == "el" ) {
                                                                                                                                                            $tone_text = "Τόνος συγγραφής: επίσημος";
                                                                                                                                                        } else {
                                                                                                                                                            
                                                                                                                                                            if ( $qcld_language == "bg" ) {
                                                                                                                                                                $tone_text = "Тон на писане: формален";
                                                                                                                                                            } else {
                                                                                                                                                                
                                                                                                                                                                if ( $qcld_language == "sv" ) {
                                                                                                                                                                    $tone_text = "Skrivton: formell";
                                                                                                                                                                } else {
                                                                                                                                                                    $tone_text = "Writing tone: formal";
                                                                                                                                                                }
                                                                                                                                                            
                                                                                                                                                            }
                                                                                                                                                        
                                                                                                                                                        }
                                                                                                                                                    
                                                                                                                                                    }
                                                                                                                                                
                                                                                                                                                }
                                                                                                                                            
                                                                                                                                            }
                                                                                                                                        
                                                                                                                                        }
                                                                                                                                    
                                                                                                                                    }
                                                                                                                                
                                                                                                                                }
                                                                                                                            
                                                                                                                            }
                                                                                                                        
                                                                                                                        }
                                                                                                                    
                                                                                                                    }
                                                                                                                
                                                                                                                }
                                                                                                            
                                                                                                            }
                                                                                                        
                                                                                                        }
                                                                                                    
                                                                                                    }
                                                                                                
                                                                                                }
                                                                                            
                                                                                            }
                                                                                        
                                                                                        }
                                                                                    
                                                                                    }
                                                                                
                                                                                }
                                                                            
                                                                            }
                                                                        
                                                                        }
                                                                    
                                                                    }
                                                                
                                                                }
                                                            
                                                            }
                                                        
                                                        }
                                                    
                                                    }
                                                
                                                }
                                            
                                            }
                                        
                                        }
                                    
                                    }
                                
                                }
                            
                            }
                        
                        }
                    
                    }
                
                }
            
            }
        
        }

	return $tone_text;
}
}

add_filter('qc_wpbotpro_floating_openai_article_heading_tag', 'qc_wpbotpro_floating_openai_article_heading_tag_callback', 10, 8 );
if ( ! function_exists( 'qc_wpbotpro_floating_openai_article_heading_tag_callback' ) ) {
function qc_wpbotpro_floating_openai_article_heading_tag_callback( $allresults, $mylist, $myprompt, $qc_wpbotpro_article_heading_tag, $style_text, $tone_text, $avoid_text, $qc_wpbotpro_article_label_word_to_avoid ){
    
      
    $OPENAI_API_KEY                     = get_option('open_ai_api_key');
    $ai_engines                         = get_option('openai_engines');
    $max_token                          = get_option('openai_max_tokens') ? get_option('openai_max_tokens') : 4000;
    $temperature                        = get_option('openai_temperature') ? get_option('openai_temperature') : 0;
    $ppenalty                           = get_option('presence_penalty');
    $fpenalty                           = get_option('frequency_penalty');

    if( isset($mylist) && !empty($mylist) ) {
        
        foreach ( $mylist as $key => $value ) {
            $withstyle = $value . '. ' . $style_text . ', ' . $tone_text . '.';
            // if avoid is not empty add it to the prompt
            if ( !empty( $qc_wpbotpro_article_label_word_to_avoid ) ) {
                $withstyle = $value . '. ' . $style_text . ', ' . $tone_text . ', ' . $avoid_text . '.';
            }

            $gptkeyword = [];
            

            if( $ai_engines == 'gpt-3.5-turbo' || $ai_engines == 'gpt-4' || $ai_engines == 'gpt-4o' || $ai_engines == 'gpt-4o-mini'){
                $ch     = curl_init();
                $url    = 'https://api.openai.com/v1/chat/completions';

                array_push($gptkeyword, array(
                           "role"       => "user",
                           "content"    =>  $myprompt
                        ));

                $post_fields = array(
                    "model"         => $ai_engines,
                    "messages"      => $gptkeyword,
                    "max_tokens"    => (int)$max_token,
                    "temperature"   => ( float ) $temperature
                );
                $header  = [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $OPENAI_API_KEY
                ];
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                $result = curl_exec($ch);
                if (curl_errno($ch)) {
                    echo esc_html('Error: ') . esc_html(curl_error($ch));
                }
                curl_close($ch);
                $complete = json_decode( $result );
                $complete = isset($complete->choices[0]->message->content) ? $complete->choices[0]->message->content : '';

            }else{

                $request_body = [
                    "prompt"            => $myprompt,
                    "model"             => $ai_engines,
                    "max_tokens"        => (int)$max_token,
                    "temperature"       => (float)$temperature,
                    "presence_penalty"  => (float)$ppenalty,
                    "frequency_penalty" => (float)$fpenalty,
                    "top_p"             => 1,
                    "best_of"           => 1,
                ];
                $data    = json_encode($request_body);
                $url     = "https://api.openai.com/v1/completions";
                $apt_key = "Authorization: Bearer ". $OPENAI_API_KEY;

                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                $headers    = array(
                   "Content-Type: application/json",
                   $apt_key ,
                );
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                $result     = curl_exec($curl);
                curl_close($curl);

                $complete = json_decode( $result );
                $complete = isset($complete->choices[0]->text) ? $complete->choices[0]->text : '';

            }
            // trim the text
            $complete = !empty($complete) ? trim( $complete ) : '';
            $value = str_replace( '\\/', '', $value );
            $value = str_replace( '\\', '', $value );
            // trim value
            $value = trim( $value );
            // we will add h tag if the user wants to

            if ( $qc_wpbotpro_article_heading_tag == "h1" ) {
                $result = "\n"."<h1>" . $value . "</h1>" ."\n". $complete;
            } elseif ( $qc_wpbotpro_article_heading_tag == "h2" ) {
                $result = "\n"."<h2>" . $value . "</h2>" ."\n". $complete;
            } elseif ( $qc_wpbotpro_article_heading_tag == "h3" ) {
                $result = "\n"."<h3>" . $value . "</h3>" ."\n". $complete;
            } elseif ( $qc_wpbotpro_article_heading_tag == "h4" ) {
                $result = "\n"."<h4>" . $value . "</h4>" ."\n". $complete;
            } elseif ( $qc_wpbotpro_article_heading_tag == "h5" ) {
                $result = "\n"."<h5>" . $value . "</h5>" ."\n". $complete;
            } elseif ( $qc_wpbotpro_article_heading_tag == "h6" ) {
                $result = "\n"."<h6>" . $value . "</h6>" ."\n". $complete;
            } else {
                $result = "\n"."<h2>" . $value . "</h2>" ."\n". $complete;
            }

            $result = preg_replace('/[\*]+/', '', $result);


            $Parsedown = new Parsedown();
            
            $allresults = $allresults . $Parsedown->text($result);
        }
    }


    return $allresults;
}
}

add_filter('qc_wpbotpro_floating_openai_article_heading_intro', 'qc_wpbotpro_floating_openai_article_heading_intro_callback', 10, 3 );
if ( ! function_exists( 'qc_wpbotpro_floating_openai_article_heading_intro_callback' ) ) {
function qc_wpbotpro_floating_openai_article_heading_intro_callback( $allresults, $myintro, $introduction ){
    
    $OPENAI_API_KEY                     = get_option('open_ai_api_key');
    $ai_engines                         = get_option('openai_engines');
    $max_token                          = get_option('openai_max_tokens') ? get_option('openai_max_tokens') : 4000;
    $temperature                        = get_option('openai_temperature') ? get_option('openai_temperature') : 0;
    $ppenalty                           = get_option('presence_penalty');
    $fpenalty                           = get_option('frequency_penalty');

    $gptkeyword = [];

    if( $ai_engines == 'gpt-3.5-turbo' || $ai_engines == 'gpt-4' || $ai_engines == 'gpt-4o' || $ai_engines == 'gpt-4o-mini'){
        $ch     = curl_init();
        $url    = 'https://api.openai.com/v1/chat/completions';

        array_push($gptkeyword, array(
                   "role"       => "user",
                   "content"    =>  $myintro
                ));

        $post_fields = array(
            "model"         => $ai_engines,
            "messages"      => $gptkeyword,
            "max_tokens"    => (int)$max_token,
            "temperature"   => ( float ) $temperature
        );
        $header  = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $OPENAI_API_KEY
        ];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo esc_html('Error: ') . esc_html(curl_error($ch));
        }
        curl_close($ch);

        $completeintro = json_decode( $result );
        
        if ( isset( $completeintro->error ) ) {
            $completeintro = $completeintro->error->message;
            // exit
            echo  esc_html( $completeintro ) ;
            exit;
        } else {
            $completeintro = isset( $completeintro->choices[0]->message->content ) ? trim($completeintro->choices[0]->message->content) : '';
            // trim the text
            $Parsedown = new Parsedown();
            $completeintro = !empty( $completeintro ) ? $Parsedown->text( $completeintro ) : '';
            // add <h1>Introuction</h1> to the beginning of the text
            $completeintro = "\n"."<h1>" . $introduction . "</h1>" ."\n". $completeintro;
            // add intro to the beginning of the text
            $allresults = $Parsedown->text($completeintro) . $allresults;
        }

    }else{

        $request_body = [
            "prompt"            => $myintro,
            "model"             => $ai_engines,
            "max_tokens"        => (int)$max_token,
            "temperature"       => (float)$temperature,
            "presence_penalty"  => (float)$ppenalty,
            "frequency_penalty" => (float)$fpenalty,
            "top_p"             => 1,
            "best_of"           => 1,
        ];
        $data    = json_encode($request_body);
        $url     = "https://api.openai.com/v1/completions";
        $apt_key = "Authorization: Bearer ". $OPENAI_API_KEY;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers    = array(
           "Content-Type: application/json",
           $apt_key ,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result     = curl_exec($curl);
        curl_close($curl);

        $completeintro = json_decode( $result );
        
        if ( isset( $completeintro->error ) ) {
            $completeintro = $completeintro->error->message;
            // exit
            echo  esc_html( $completeintro ) ;
            exit;
        } else {

            $completeintro = isset( $completeintro->choices[0]->text ) ? trim( $completeintro->choices[0]->text ) : '';
            // trim the text
            $Parsedown = new Parsedown();
            $completeintro = !empty( $completeintro ) ? $Parsedown->text( $completeintro ) : '';
            // add <h1>Introuction</h1> to the beginning of the text
            $completeintro = "\n"."<h1>" . $introduction . "</h1>" ."\n". $completeintro;
            // add intro to the beginning of the text
            $allresults = $Parsedown->text($completeintro) . $allresults;
        }

    }


    return $allresults;
}
}

add_filter('qc_wpbotpro_floating_openai_article_heading_faq', 'qc_wpbotpro_floating_openai_article_heading_faq_callback', 10, 3 );
if ( ! function_exists( 'qc_wpbotpro_floating_openai_article_heading_faq_callback' ) ) {
function qc_wpbotpro_floating_openai_article_heading_faq_callback( $allresults, $faq_text, $faq_heading ){
    
    $OPENAI_API_KEY                     = get_option('open_ai_api_key');
    $ai_engines                         = get_option('openai_engines');
    $max_token                          = get_option('openai_max_tokens') ? get_option('openai_max_tokens') : 4000;
    $temperature                        = get_option('openai_temperature') ? get_option('openai_temperature') : 0;
    $ppenalty                           = get_option('presence_penalty');
    $fpenalty                           = get_option('frequency_penalty');

    $gptkeyword = [];

    if( $ai_engines == 'gpt-3.5-turbo' || $ai_engines == 'gpt-4' || $ai_engines == 'gpt-4o' || $ai_engines == 'gpt-4o-mini'){
        $ch     = curl_init();
        $url    = 'https://api.openai.com/v1/chat/completions';

        array_push($gptkeyword, array(
                   "role"       => "user",
                   "content"    =>  $faq_text
                ));

        $post_fields = array(
            "model"         => $ai_engines,
            "messages"      => $gptkeyword,
            "max_tokens"    => (int)$max_token,
            "temperature"   => ( float ) $temperature
        );
        $header  = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $OPENAI_API_KEY
        ];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
           echo esc_html('Error: ') . esc_html(curl_error($ch));
        }
        curl_close($ch);

        $completeintro = json_decode( $result );
        
        if ( isset( $completefaq->error ) ) {
            $completefaq = $completefaq->error->message;
            // exit
            echo  esc_html( $completefaq ) ;
            exit;
        } else {
            //$completefaq = $complete->choices[0]->message->content;
            $completefaq = isset( $complete->choices[0]->message->content ) ? trim( $complete->choices[0]->message->content ) : '';
            // trim the text
            $completefaq = !empty( $completefaq ) ? trim( $completefaq ) : '';
            // add <h1>FAQ</h1> to the beginning of the text
            $completefaq = "\n"."<h2>" . $faq_heading . "</h2>" ."\n". $completefaq;
            // add intro to the beginning of the text
            $allresults = $allresults . $completefaq;
        }

    }else{

        $request_body = [
            "prompt"            => $faq_text,
            "model"             => $ai_engines,
            "max_tokens"        => (int)$max_token,
            "temperature"       => (float)$temperature,
            "presence_penalty"  => (float)$ppenalty,
            "frequency_penalty" => (float)$fpenalty,
            "top_p"             => 1,
            "best_of"           => 1,
        ];
        $data    = json_encode($request_body);
        $url     = "https://api.openai.com/v1/completions";
        $apt_key = "Authorization: Bearer ". $OPENAI_API_KEY;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers    = array(
           "Content-Type: application/json",
           $apt_key ,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result     = curl_exec($curl);
        curl_close($curl);

        $completefaq = json_decode( $result );
        
        if ( isset( $completefaq->error ) ) {
            $completefaq = $completefaq->error->message;
            // exit
            echo  esc_html( $completefaq ) ;
            exit;
        } else {
            //$completefaq = $completefaq->choices[0]->text;
            $completefaq = isset( $completefaq->choices[0]->text ) ? trim( $completefaq->choices[0]->text ) : '';
            // trim the text
            $Parsedown = new Parsedown();
            $completefaq = !empty( $completefaq ) ? $Parsedown->text( $completefaq ) : '';
            // add <h1>FAQ</h1> to the beginning of the text
            $completefaq = "\n"."<h2>" . $faq_heading . "</h2>" ."\n". $completefaq;
            // add intro to the beginning of the text
            $allresults = $allresults . $completefaq;
        }
    
    }

    return $allresults;

}
}

add_filter('qc_wpbotpro_floating_openai_article_heading_conclusion', 'qc_wpbotpro_floating_openai_article_heading_conclusion_callback', 10, 3 );
if ( ! function_exists( 'qc_wpbotpro_floating_openai_article_heading_conclusion_callback' ) ) {
function qc_wpbotpro_floating_openai_article_heading_conclusion_callback( $allresults, $myconclusion, $conclusion ){
    
    $OPENAI_API_KEY                     = get_option('open_ai_api_key');
    $ai_engines                         = get_option('openai_engines');
    $max_token                          = get_option('openai_max_tokens') ? get_option('openai_max_tokens') : 4000;
    $temperature                        = get_option('openai_temperature') ? get_option('openai_temperature') : 0;
    $ppenalty                           = get_option('presence_penalty');
    $fpenalty                           = get_option('frequency_penalty');

    $gptkeyword = [];

    if( $ai_engines == 'gpt-3.5-turbo' || $ai_engines == 'gpt-4' || $ai_engines == 'gpt-4o' || $ai_engines == 'gpt-4o-mini'){
        $ch     = curl_init();
        $url    = 'https://api.openai.com/v1/chat/completions';

        array_push($gptkeyword, array(
                   "role"       => "user",
                   "content"    =>  $myconclusion
                ));

        $post_fields = array(
            "model"         => $ai_engines,
            "messages"      => $gptkeyword,
            "max_tokens"    => (int)$max_token,
            "temperature"   => ( float ) $temperature
        );
        $header  = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $OPENAI_API_KEY
        ];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
           echo esc_html('Error: ') . esc_html(curl_error($ch));
        }
        curl_close($ch);
        //$complete = json_decode( $result );
       // $complete = isset($complete->choices[0]->message->content) ? $complete->choices[0]->message->content : '';

        $completeconclusion = json_decode( $result );
        
        if ( isset( $completeconclusion->error ) ) {
            $completeconclusion = $completeconclusion->error->message;
            // exit
            echo  esc_html( $completeconclusion ) ;
            exit;
        } else {
            //$completeconclusion = $complete->choices[0]->message->content;
            $completeconclusion = isset( $completeconclusion->choices[0]->message->content ) ? trim( $completeconclusion->choices[0]->message->content ) : '';
            // trim the text
            $Parsedown = new Parsedown();
            $completeconclusion = !empty( $completeconclusion ) ? $Parsedown->text( $completeconclusion ) : '';
            // add <h1>Conclusion</h1> to the beginning of the text
            $completeconclusion = "\n"."<h1>" . $conclusion . "</h1>" ."\n". $completeconclusion;
            // add intro to the beginning of the text
            $allresults = $allresults . $completeconclusion;
        }

    }else{

        // we need to catch the error here
        $request_body = [
            "prompt"            => $myconclusion,
            "model"             => $ai_engines,
            "max_tokens"        => (int)$max_token,
            "temperature"       => (float)$temperature,
            "presence_penalty"  => (float)$ppenalty,
            "frequency_penalty" => (float)$fpenalty,
            "top_p"             => 1,
            "best_of"           => 1,
        ];
        $data    = json_encode($request_body);
        $url     = "https://api.openai.com/v1/completions";
        $apt_key = "Authorization: Bearer ". $OPENAI_API_KEY;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers    = array(
           "Content-Type: application/json",
           $apt_key ,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result     = curl_exec($curl);
        curl_close($curl);

        $completeconclusion = json_decode( $result );
        
        if ( isset( $completeconclusion->error ) ) {
            $completeconclusion = $completeconclusion->error->message;
            // exit
            echo  esc_html( $completeconclusion ) ;
            exit;
        } else {
            //$completeconclusion = $completeconclusion->choices[0]->text;
            $completeconclusion = isset( $completeconclusion->choices[0]->text ) ? trim( $completeconclusion->choices[0]->text ) : '';
            // trim the text
            $Parsedown = new Parsedown();
            $completeconclusion = !empty( $completeconclusion ) ? $Parsedown->text( $completeconclusion ) : '';
            // add <h1>Conclusion</h1> to the beginning of the text
            $completeconclusion = "\n"."<h1>" . $conclusion . "</h1>" ."\n". $completeconclusion;
            // add intro to the beginning of the text
            $allresults = $allresults . $completeconclusion;
        }
    }

    return $allresults;
    
}
}

add_filter('qc_wpbotpro_floating_openai_article_heading_tagline', 'qc_wpbotpro_floating_openai_article_heading_tagline_callback', 10, 3 );
if ( ! function_exists( 'qc_wpbotpro_floating_openai_article_heading_tagline_callback' ) ) {
function qc_wpbotpro_floating_openai_article_heading_tagline_callback( $allresults, $mytagline, $conclusion ){
    
    $OPENAI_API_KEY                     = get_option('open_ai_api_key');
    $ai_engines                         = get_option('openai_engines');
    $max_token                          = get_option('openai_max_tokens') ? get_option('openai_max_tokens') : 4000;
    $temperature                        = get_option('openai_temperature') ? get_option('openai_temperature') : 0;
    $ppenalty                           = get_option('presence_penalty');
    $fpenalty                           = get_option('frequency_penalty');

    $gptkeyword = [];

    if( $ai_engines == 'gpt-3.5-turbo' || $ai_engines == 'gpt-4' || $ai_engines == 'gpt-4o' || $ai_engines == 'gpt-4o-mini'){
        $ch     = curl_init();
        $url    = 'https://api.openai.com/v1/chat/completions';

        array_push($gptkeyword, array(
                   "role"       => "user",
                   "content"    =>  $mytagline
                ));

        $post_fields = array(
            "model"         => $ai_engines,
            "messages"      => $gptkeyword,
            "max_tokens"    => (int)$max_token,
            "temperature"   => ( float ) $temperature
        );
        $header  = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $OPENAI_API_KEY
        ];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
           echo esc_html('Error: ') . esc_html(curl_error($ch));
        }
        curl_close($ch);
        //$complete = json_decode( $result );
       // $complete = isset($complete->choices[0]->message->content) ? $complete->choices[0]->message->content : '';

        $completetagline = json_decode( $result );
        
        if ( isset( $completetagline->error ) ) {
            $completetagline = $completetagline->error->message;
            // exit
            echo  esc_html( $completetagline ) ;
            exit;
        } else {
            $completetagline = isset( $completetagline->choices[0]->message->content ) ? trim( $completetagline->choices[0]->message->content ) : '';
            // trim the text
            $Parsedown = new Parsedown();
            $completetagline = !empty($completetagline) ? $Parsedown->text( $completetagline ) : '';
            // add <p> to the beginning of the text
            $completetagline = "\n"."<p>" . $completetagline . "</p>"."\n";
            // add intro to the beginning of the text
            $allresults = $completetagline . $allresults;
        }

    }else{

        // we need to catch the error here
        $request_body = [
            "prompt"            => $mytagline,
            "model"             => $ai_engines,
            "max_tokens"        => (int)$max_token,
            "temperature"       => (float)$temperature,
            "presence_penalty"  => (float)$ppenalty,
            "frequency_penalty" => (float)$fpenalty,
            "top_p"             => 1,
            "best_of"           => 1,
        ];
        $data    = json_encode($request_body);
        $url     = "https://api.openai.com/v1/completions";
        $apt_key = "Authorization: Bearer ". $OPENAI_API_KEY;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers    = array(
           "Content-Type: application/json",
           $apt_key ,
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result     = curl_exec($curl);
        curl_close($curl);

        $completetagline = json_decode( $result );
        
        if ( isset( $completetagline->error ) ) {
            $completetagline = $completetagline->error->message;
            // exit
            echo  esc_html( $completetagline ) ;
            exit;
        } else {
            //$completetagline = $completetagline->choices[0]->text;
            $completetagline = isset( $completetagline->choices[0]->text ) ? trim( $completetagline->choices[0]->text ) : '';
            // trim the text
            $Parsedown = new Parsedown();
            $completetagline = !empty($completetagline) ? $Parsedown->text( $completetagline ) : '';
            // add <p> to the beginning of the text
            $completetagline = "\n"."<p>" . $completetagline . "</p>"."\n";
            // add intro to the beginning of the text
            $allresults = $completetagline . $allresults;
        }

    }

    return $allresults;
    
}
}

add_filter('qc_wpbotpro_floating_openai_article_heading_img', 'qc_wpbotpro_floating_openai_article_heading_img_callback', 10, 2 );
if ( ! function_exists( 'qc_wpbotpro_floating_openai_article_heading_img_callback' ) ) {
function qc_wpbotpro_floating_openai_article_heading_img_callback( $qc_wpbotpro_article_text, $img_size ){
    
    $OPENAI_API_KEY                     = get_option('open_ai_api_key');
    $ai_engines                         = get_option('openai_engines');
    $max_token                          = get_option('openai_max_tokens') ? get_option('openai_max_tokens') : 4000;
    $temperature                        = get_option('openai_temperature') ? get_option('openai_temperature') : 0;
    $ppenalty                           = get_option('presence_penalty');
    $fpenalty                           = get_option('frequency_penalty');

    $imgresult = '';

    $request_body = [
        "prompt"            => $qc_wpbotpro_article_text,
        "model"             => 'dall-e-3',
        "n"                 => 1,
        "size"              => $img_size,
        "response_format"   => "url",
    ];
    $data    = json_encode($request_body);
    $url     = "https://api.openai.com/v1/images/generations";
    $apt_key = "Authorization: Bearer ". $OPENAI_API_KEY;

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $headers    = array(
       "Content-Type: application/json",
       $apt_key,
    );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    $result     = curl_exec($curl);
    curl_close($curl);

    // we need to catch the error here
    $imgresult = json_decode( $result );


    $imgresult = isset($imgresult->data[0]->url) ? $imgresult->data[0]->url : '';

    if( !empty($imgresult)){

        $array              = explode('/', getimagesize($imgresult)['mime']);
        $imagetype          = end($array);
        $uniq_name          = md5($imgresult);
        $filename           = $uniq_name . '.' . $imagetype;

        $uploaddir          = wp_upload_dir();
        $target_file_name   = $uploaddir['path'] . '/' . $filename;

        $contents           = file_get_contents( $imgresult );
        $savefile           = fopen($target_file_name, 'w');
        fwrite($savefile, $contents);
        fclose($savefile);

        /* add the image title */
        $image_title        = ucwords( $uniq_name );

        $qc_wpbotpro_floating_openai_images_attribution = 'gpt openai';

        /* add the caption */
        $attachment_caption = '';
        if (! isset($qc_wpbotpro_floating_openai_images_attribution['attribution']) | isset($qc_wpbotpro_floating_openai_images_attribution['attribution']) == 'true')
            $attachment_caption = '<a href="' . esc_url( $imgresult ) . '" target="_blank" rel="noopener">' . esc_attr( $filename ) . '</a>';

        unset($imgresult);

        /* insert the attachment */
        $wp_filetype = wp_check_filetype(basename($target_file_name), null);
        $attachment  = array(
            'guid'              => $uploaddir['url'] . '/' . basename($target_file_name),
            'post_mime_type'    => $wp_filetype['type'],
            'post_title'        => $image_title,
            'post_status'       => 'inherit'
        );
        $post_id     = isset($_REQUEST['post_id']) ? absint($_REQUEST['post_id']): '';
        $attach_id   = wp_insert_attachment($attachment, $target_file_name, $post_id);
        if ($attach_id == 0)
            die('Error: File attachment error');

        $attach_data = wp_generate_attachment_metadata($attach_id, $target_file_name);
        $result      = wp_update_attachment_metadata($attach_id, $attach_data);

        $image_data                 = array();
        $image_data['ID']           = $attach_id;
        $image_data['post_excerpt'] = $attachment_caption;
        wp_update_post($image_data);

        $parsed = wp_get_attachment_image_src( $attach_id, 'full' )[0];

        if(!empty($parsed)){
            $attach_id = $parsed;
        }

        $imgresult = "\n"."<img src='" . $attach_id . "' alt='" . $qc_wpbotpro_article_text . "' />"."\n";

    }

    return $imgresult;
    
}
}