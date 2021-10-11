<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;

use App\Models\Users;
use App\Mail\EmailVerification;
use Exception;

class Auth extends Controller
{
    # menampilkan halaman login users
    public function userslogin(){

        return view('web/auth/login')->with(['title'=>'Login']);

    }

    # menampilkan halaman register users
    public function usersregister(){

        return view('web/auth/register')->with(['title'=>'Register']);

    }

    # proses handler registrasi users
    public function usersregister_handler(Request $request){

        # Validator
        $validator = Validator::make($request->all(),[
            'nama_user' => ['required', 'max:100'],
            'email' => ['required', 'unique:users', 'email'],
            'password' => ['required', Password::min(6)->mixedCase()->numbers()->letters()]
        ]);

        # If Else Apabila Validasi Salah dan Benar
        if($validator->fails()){

            # Jika Validasi Salah, Redirect ke halaman registrasi dengan pesan error
            return redirect('register')->withErrors($validator)->withInput();

        }else{

            # Jika Validasi Berhasil Maka
            $data = [
                'nama_user' => $request->post('nama_user'),
                'email' => $request->post('email'),
                'password' => password_hash($request->post('password'), PASSWORD_DEFAULT),
                'status' => 'off'
            ];

            # Insert Data Tabel Users
            Users::create($data);

            try{

                # Kirim Link Aktifasi Akun, Lewat Email
                Mail::to($request->post('email'))->send(new EmailVerification(['email'=>$request->post('email'), 'nama_user'=>$request->post('nama_user')]));

            }catch(Exception $e){

                # Tampilkan pesan error
                dd($e->getMessage());

            }

            # Redirect ke halaman registrasi dengan pesan sukses
            return redirect('register')->with('success', 'Kami telah mengirimkan link aktifasi akun anda ke email '.$request->post('email'));

        }

    }

    # proses aktifasi akun users
    public function usersverification(Request $request){

        # Update Status Akun off -> on
        Users::where('email', $request->segment(2))->update(['status'=>'on']);

        # Redirect ke halaman login dengan pesan sukses
        return redirect('login')->with('success', 'Selamat Akun anda Berhasil di Aktifasi');

    }

    # Login Manual
    public function userslogin_handler(Request $request){

        # Validator
        $validator = Validator::make($request->all(),[
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);

        # if else validator salah atau benar
        if($validator->fails()){

            # jika validasi error kembali ke laman admin dengan pesan error
            return redirect('login')->withErrors($validator);

        }

        $data = [
            'email' => $request->post('email'),
            'password' => $request->post('password'),
            'status' => 'on'
        ];

        if(\Illuminate\Support\Facades\Auth::attempt($data)){

            # Dapatkan data users
            $isUser = Users::where('email', $request->post('email'))->first();

            # set session
            $session = array(
                'isUsers' => true,
                'id_users' => $isUser->id_users,
                'nama_user' => $isUser->nama_user
            );
            
            # simpan session
            $request->session()->put($session);

            # redirect ke laman dashboard pembeli
            return redirect('index');

        }else{

            # Redirect ke halaman login dengan pesan error
            return redirect('login')->with('error', 'Email atau Password Salah');

        }


    }

    # jika login / register via google di klik
    public function google(){

        # google auth redirect
        return Socialite::driver('google')->redirect();

    }

    # callback google
    public function google_callback(Request $request){

        try{

            $user = Socialite::driver('google')->user();

            # cek di tabel users apakah value di kolom google_id sudah ada, atau belum
            $isUser = Users::where('google_id', $user->id)->first();

            # jika sudah ada, redirect ke halaman dashboard users (Berhasil Login)
            if($isUser){

                # set session
                $session = array(
                    'isUsers' => true,
                    'id_users' => $isUser->id_users,
                    'nama_user' => $isUser->nama_user
                );

                # simpan session
                $request->session()->put($session);

                # redirect ke laman dashboard pembeli
                return redirect('index');

            }else{

                # Jika kolom google_id di tabel users masih null value nya, registerkan secara otomatis
                # Get Email dari Google
                if($user->getEmail() != null){

                    # Cek email users udah pernah registrasi atau belum
                    $cekusers = Users::where('email', $user->getEmail())->get();
                    
                    if($cekusers->count() > 0){

                        # Jika email telah didaftarakan, tambahkan google_id
                        Users::where('email', $user->getEmail())->update(['google_id'=>$user->getId()]);

                    }else{

                        # Jika Email sama sekali belum pernah didaftarkan Lakukan Registrasi Otomatis
                        $data = array(
                            'nama_user' => $user->getName(),
                            'email' => $user->getEmail(),
                            'password' => password_hash(rand(0, 1000), PASSWORD_DEFAULT),
                            'status' => 'on',
                            'google_id' => $user->getId()
                        );

                        # Tambah Akun Users Baru
                        Users::create($data);

                    }

                    # Dapatkan data users
                    $isUser = Users::where('google_id', $user->id)->first();

                    # set session
                    $session = array(
                        'isUsers' => true,
                        'id_users' => $isUser->id_users,
                        'nama_user' => $isUser->nama_user
                    );
                    
                    # simpan session
                    $request->session()->put($session);

                    # redirect ke laman dashboard pembeli
                    return redirect('index');

                } 

            }

        }catch(Exception $e){

            # Tampilkan pesan error
            dd($e->getMessage());

        }

    }

    # Logout Semua Akun
    public function logout(Request $request){
        $request->session()->flush();
        return redirect('/');
    }

}
